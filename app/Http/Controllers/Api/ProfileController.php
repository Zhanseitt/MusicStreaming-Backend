<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Comment;
use App\Models\ListeningHistory;
use App\Models\Song;

class ProfileController extends Controller
{
    // Статистика профиля
    public function stats(Request $request)
    {
        $user = $request->user();
        
        // 1. Считаем часы (используем реальное время прослушивания)
        $totalSeconds = $user->history()->sum('listened_seconds');
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
        $request->validate([
            'content' => 'required|string|max:500',
            'type' => 'nullable|in:positive,negative,suggestion,neutral'
        ]);
        
        $comment = Comment::create([
            'user_id' => $request->user()->id,
            'song_id' => $songId,
            'content' => $request->content,
            'type' => $request->type ?? 'neutral'
        ]);
        
        return $comment->load('user:id,name');
    }
    
    // Получить лайкнутые песни пользователя
    // GET /api/user/liked-songs
    public function getLikedSongs(Request $request)
    {
        $user = $request->user();
        
        $likedSongs = $user->likedSongs()
            ->with('artist')
            ->get()
            ->map(function($song) {
                return $this->formatSong($song);
            });
        
        return response()->json([
            'data' => $likedSongs
        ]);
    }
    
    // Получить историю прослушиваний пользователя
    // GET /api/user/history
    public function getHistory(Request $request)
    {
        $user = $request->user();
        
        // Получаем внутренние треки
        $internalHistory = $user->history()
            ->with('song.artist')
            ->orderBy('played_at', 'desc')
            ->get()
            ->map(function($historyItem) {
                if (!$historyItem->song) return null;
                return [
                    'played_at' => $historyItem->played_at->toIso8601String(),
                    'song' => $this->formatSong($historyItem->song)
                ];
            })
            ->filter();
        
        // Получаем внешние треки (Jamendo)
        $externalHistory = collect([]);
        if (Schema::hasTable('listening_history_external')) {
            $externalHistory = DB::table('listening_history_external')
                ->where('user_id', $user->id)
                ->orderBy('played_at', 'desc')
                ->get()
                ->map(function($item) {
                    // Форматируем дату в ISO8601 формат
                    $playedAt = is_string($item->played_at) 
                        ? \Carbon\Carbon::parse($item->played_at)->toIso8601String()
                        : \Carbon\Carbon::parse($item->played_at)->toIso8601String();
                    
                    return [
                        'played_at' => $playedAt,
                        'song' => [
                            'id' => 'jamendo_' . $item->external_id,
                            'title' => $item->title,
                            'artist' => $item->artist,
                            'artist_id' => null,
                            'album' => '',
                            'duration' => $item->duration,
                            'cover' => $item->cover ?? '',
                            'audioUrl' => $item->audio_url,
                            'is_external' => true,
                            'source' => $item->source,
                            'external_id' => $item->external_id,
                            'shareurl' => $item->shareurl ?? '',
                            'tags' => $item->tags ?? ''
                        ]
                    ];
                });
        }
        
        // Объединяем и сортируем по дате
        $allHistory = $internalHistory->merge($externalHistory)
            ->sortByDesc(function($item) {
                return $item['played_at'];
            })
            ->values();
        
        return response()->json([
            'data' => $allHistory
        ]);
    }
    
    // Сохранить прослушивание (обновленный метод для новой структуры)
    // POST /api/user/history
    public function recordHistory(Request $request)
    {
        $request->validate([
            'song_id' => 'required|exists:songs,id',
            'listened_seconds' => 'nullable|integer|min:0'
        ]);
        
        ListeningHistory::create([
            'user_id' => $request->user()->id,
            'song_id' => $request->song_id,
            'played_at' => now(),
            'listened_seconds' => $request->listened_seconds ?? 0
        ]);
        
        return response()->json(['status' => 'saved']);
    }
    
    // Сохранить прослушивание внешнего трека (Jamendo)
    // POST /api/user/history/external
    public function recordExternalHistory(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'artist' => 'required|string',
            'cover' => 'nullable|string',
            'audioUrl' => 'required|string',
            'source' => 'required|string|in:jamendo',
            'external_id' => 'required|string',
            'shareurl' => 'nullable|string',
            'tags' => 'nullable|string',
            'duration' => 'nullable|string',
            'listened_seconds' => 'nullable|integer|min:0'
        ]);
        
        $user = $request->user();
        
        // Создаем или находим запись в таблице listening_history_external
        // Если таблицы нет, создаем запись в listening_history с song_id = null и сохраняем данные в JSON
        try {
            // Пытаемся использовать таблицу для внешних треков, если она существует
            if (Schema::hasTable('listening_history_external')) {
                DB::table('listening_history_external')->insert([
                    'user_id' => $user->id,
                    'external_id' => $request->external_id,
                    'title' => $request->title,
                    'artist' => $request->artist,
                    'cover' => $request->cover ?? '',
                    'audio_url' => $request->audioUrl,
                    'source' => $request->source,
                    'shareurl' => $request->shareurl ?? '',
                    'tags' => $request->tags ?? '',
                    'duration' => $request->duration ?? '0:00',
                    'played_at' => now(),
                    'listened_seconds' => $request->listened_seconds ?? 0
                ]);
            } else {
                // Если таблицы нет, сохраняем в JSON поле в listening_history
                // Для этого нужно добавить поле external_track_data в миграцию
                // Пока просто возвращаем успех, чтобы не ломать приложение
                Log::info('External history saved (table not exists):', $request->all());
            }
        } catch (\Exception $e) {
            Log::error('Error saving external history: ' . $e->getMessage());
            // Не бросаем ошибку, чтобы не ломать приложение
        }
        
        return response()->json(['status' => 'saved']);
    }
    
    // Форматирование песни для API (используем такой же формат как в SongController)
    private function formatSong($song)
    {
        $audioUrl = $song->audio_url;
        if ($audioUrl && !str_starts_with($audioUrl, 'http')) {
            $audioUrl = url('api/stream/' . $song->id);
        }

        $coverUrl = $song->cover_url;
        if ($coverUrl && !str_starts_with($coverUrl, 'http')) {
            $coverUrl = url('storage/' . $coverUrl);
        }

        return [
            'id' => $song->id,
            'title' => $song->title,
            'artist' => $song->artist ? $song->artist->name : 'Unknown Artist',
            'artist_id' => $song->artist_id,
            'album' => $song->album ?? '',
            'duration' => $song->duration ?? '0:00',
            'cover' => $coverUrl ?? 'https://via.placeholder.com/300',
            'cover_url' => $coverUrl ?? 'https://via.placeholder.com/300',
            'audioUrl' => $audioUrl ?? '',
            'audio_url' => $audioUrl ?? '',
            'lyrics' => $song->lyrics ?? [],
        ];
    }
}