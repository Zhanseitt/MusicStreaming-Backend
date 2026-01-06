<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Song;
use App\Models\Genre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SongController extends Controller
{
    /**
     * Список всех песен
     * GET /api/songs
     */
    public function index(Request $request)
    {
        Log::info('GET /api/songs');

        $query = Song::with('artist')
            ->where('status', 'approved')
            ->latest();

        // Фильтр по жанру:
        // - новый поток: связь many-to-many через genre_song
        // - fallback: старое поле songs.genre (строка)
        $genreParam = $request->query('genre');
        if (!empty($genreParam)) {
            $genreModel = null;

            // Если пришёл числовой genre, трактуем как genre_id
            if (is_numeric($genreParam)) {
                $genreModel = Genre::find((int) $genreParam);
            }

            // Иначе пытаемся найти по имени (без учёта регистра)
            if (!$genreModel) {
                $genreModel = Genre::whereRaw('LOWER(name) = ?', [mb_strtolower((string) $genreParam)])->first();
            }

            if ($genreModel) {
                $query->whereHas('genres', function ($q) use ($genreModel) {
                    $q->where('genres.id', $genreModel->id);
                });
            } else {
                // Fallback на старое строковое поле
                $query->where('genre', $genreParam);
            }
        }

        $songs = $query->get();

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
            ->where('status', 'approved')
            ->where(function($q) use ($query) {
                $q->where('title', 'LIKE', "%{$query}%")
                  ->orWhere('album', 'LIKE', "%{$query}%")
                  ->orWhereHas('artist', function($q2) use ($query) {
                      $q2->where('name', 'LIKE', "%{$query}%");
                  });
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
        // Исключаем новые релизы (первые 10 по дате создания), чтобы не пересекаться
        $newReleaseIds = Song::where('status', 'approved')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->pluck('id')
            ->toArray();
        
        // Берем популярные песни из нашей БД, исключая новые релизы (10 песен)
        $localSongs = Song::with('artist')
            ->where('status', 'approved')
            ->whereNotIn('id', $newReleaseIds)
            ->orderBy('play_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function($song) {
                return $this->formatSong($song);
            });

        // Получаем трендовые треки из Jamendo (10 песен)
        $jamendoSongs = $this->getJamendoTrending(10);

        // Объединяем и перемешиваем
        $allSongs = $localSongs->merge($jamendoSongs)->shuffle()->take(20);

        return response()->json([
            'data' => $allSongs->values()
        ]);
    }

    /**
     * Получить трендовые треки из Jamendo
     */
    private function getJamendoTrending($limit = 10)
    {
        $clientId = config('services.jamendo.client_id') ?? env('JAMENDO_CLIENT_ID');
        
        if (!$clientId) {
            // JAMENDO_CLIENT_ID не установлен - это нормально, если не используется Jamendo API
            // Просто возвращаем пустой массив без логирования
            return collect([]);
        }

        try {
            $response = Http::get('https://api.jamendo.com/v3.0/tracks/', [
                'client_id' => $clientId,
                'format' => 'json',
                'limit' => $limit,
                'order' => 'popularity_total_desc',
                'tags' => '',
                'boost' => 'popularity_total'
            ]);

            if ($response->successful()) {
                $results = $response->json('results', []);
                return collect($results)->map(function($track) {
                    return [
                        'id' => 'jamendo_' . $track['id'],
                        'title' => $track['name'] ?? '',
                        'artist' => $track['artist_name'] ?? 'Unknown',
                        'artist_id' => null,
                        'album' => $track['album_name'] ?? '',
                        'duration' => $this->formatJamendoDuration($track['duration'] ?? 0),
                        'cover' => $track['image'] ?? 'https://via.placeholder.com/300',
                        'audioUrl' => $track['audio'] ?? '',
                        'source' => 'jamendo',
                        'jamendoData' => [
                            'shareurl' => $track['shareurl'] ?? '',
                            'tags' => $track['tags'] ?? ''
                        ]
                    ];
                });
            }
        } catch (\Exception $e) {
            Log::error('Ошибка при получении трендов из Jamendo: ' . $e->getMessage());
        }

        return collect([]);
    }

    /**
     * Форматирование длительности из секунд для Jamendo
     */
    private function formatJamendoDuration($seconds)
    {
        $minutes = floor($seconds / 60);
        $secs = floor($seconds % 60);
        return $minutes . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT);
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
