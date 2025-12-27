<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Comment;
use App\Models\ListeningHistory;

class ProfileController extends Controller
{
    // Статистика профиля
    public function stats(Request $request)
    {
        $user = $request->user();
        
        // 1. Считаем часы
        $totalSeconds = $user->history()->sum('duration_seconds');
        $hoursStreamed = round($totalSeconds / 3600, 1);

        // 2. История (последние 10 треков)
        $history = $user->history()
                        ->with('song.artist')
                        ->latest()
                        ->limit(10)
                        ->get()
                        ->map(function($h) {
                            if (!$h->song) return null;
                            return [
                                'id' => $h->id,
                                'title' => $h->song->title,
                                'artist' => $h->song->artist ? $h->song->artist->name : 'Unknown',
                                'cover' => $h->song->cover_url,
                                'time' => $h->created_at->diffForHumans()
                            ];
                        })->filter()->values();

        // 3. Плейлисты (для отображения в профиле)
        $playlists = $user->playlists()->get()->map(function($p){
             return [
                'id' => $p->id,
                'name' => $p->name,
                'color' => $p->color,
             ];
        });

        return response()->json([
            'playlists_count' => $playlists->count(),
            'followers_count' => 0, 
            'following_count' => 0,
            'hours_streamed' => $hoursStreamed,
            'history' => $history,
            'playlists' => $playlists // <--- ВАЖНО: передаем массив плейлистов
        ]);
    }

    // Сохранить прослушивание
    public function recordHistory(Request $request)
    {
        $request->validate(['song_id' => 'required', 'duration' => 'numeric']);
        
        ListeningHistory::create([
            'user_id' => $request->user()->id,
            'song_id' => $request->song_id,
            'duration_seconds' => $request->duration
        ]);
        
        return response()->json(['status' => 'saved']);
    }

    // Очистить историю
    public function clearHistory(Request $request)
    {
        $request->user()->history()->delete();
        return response()->json(['status' => 'cleared']);
    }

    // Комментарии (получить)
    public function getComments($songId)
    {
        return Comment::where('song_id', $songId)->with('user:id,name')->latest()->get();
    }

    // Комментарии (добавить)
    public function addComment(Request $request, $songId)
    {
        $request->validate(['content' => 'required|string|max:500']);
        
        $comment = Comment::create([
            'user_id' => $request->user()->id,
            'song_id' => $songId,
            'content' => $request->content
        ]);
        
        return $comment->load('user:id,name');
    }
}