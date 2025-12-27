<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Song;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SongController extends Controller
{
    /**
     * Список всех песен
     * GET /api/songs
     */
    public function index()
    {
        Log::info('GET /api/songs');

        $songs = Song::with('artist')->latest()->get();

        return response()->json([
            'data' => $songs->map(function($song) {
                return $this->formatSong($song);
            })
        ]);
    }

    /**
     * Детальная информация о песне
     * GET /api/songs/{song}
     */
    public function show(Song $song)
    {
        $song->load('artist');
        return response()->json($this->formatSong($song));
    }

    /**
     * Поиск песен
     * GET /api/songs/search?q=query
     */
    public function search(Request $request)
    {
        $query = $request->input('q');

        if (!$query) {
            return response()->json(['data' => []]);
        }

        $songs = Song::with('artist')
            ->where('title', 'LIKE', "%{$query}%")
            ->orWhere('album', 'LIKE', "%{$query}%")
            ->orWhereHas('artist', function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%");
            })
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $songs->map(function($song) {
                return $this->formatSong($song);
            })
        ]);
    }

    /**
     * Трендовые песни
     * GET /api/songs/trending
     */
    public function trending()
    {
        $songs = Song::with('artist')
            ->orderBy('play_count', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $songs->map(function($song) {
                return $this->formatSong($song);
            })
        ]);
    }

    /**
     * Потоковая передача аудио
     * GET /api/stream/{id}
     */
    public function stream($id)
    {
        $song = Song::findOrFail($id);
        
        $audioPath = $song->audio_url;
        
        if (str_starts_with($audioPath, 'http')) {
            return redirect($audioPath);
        }
        
        $path = storage_path('app/public/' . $audioPath);
        
        if (!file_exists($path)) {
            abort(404, 'Audio file not found: ' . $audioPath);
        }

        // Увеличиваем счётчик прослушиваний
        $song->increment('play_count');

        return response()->file($path, [
            'Content-Type' => 'audio/mpeg',
            'Accept-Ranges' => 'bytes',
        ]);
    }

    /**
     * Лайк/анлайк песни
     * POST /api/like/song/{song}
     */
    public function toggleLike(Request $request, Song $song)
    {
        $request->user()->likedSongs()->toggle($song->id);
        
        return response()->json([
            'status' => 'success',
            'liked' => $request->user()->likedSongs()->where('song_id', $song->id)->exists()
        ]);
    }

    /**
     * Форматирование песни для API
     */
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
            'audioUrl' => $audioUrl ?? '',
            'lyrics' => $song->lyrics ?? [],
            'color' => $song->color ?? null,
        ];
    }
}
