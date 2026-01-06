<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use App\Models\Song;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscoverController extends Controller
{
    /**
     * Daily Mixes
     * GET /api/discover/daily-mixes
     */
    public function dailyMixes()
    {
        // Берём жанры из нашей БД (одобренные админом). Fallback — дефолтный набор.
        $genres = Genre::orderBy('name')->limit(6)->pluck('name')->toArray();
        if (empty($genres)) {
            $genres = ['Pop', 'Rock', 'Hip-Hop', 'Electronic', 'Jazz', 'Classical'];
        }
        
        $mixes = [];
        foreach ($genres as $index => $genre) {
            $songs = Song::with('artist')
                ->where('status', 'approved')
                ->where('genre', $genre)
                ->inRandomOrder()
                ->limit(30)
                ->get();
                
            if ($songs->isNotEmpty()) {
                $mixes[] = [
                    'id' => $index + 1,
                    'name' => 'Daily Mix ' . ($index + 1),
                    'description' => ucfirst($genre) . ' hits',
                    'cover' => $this->getCoverUrl($songs->first()->cover_url),
                    'gradient' => $this->getGradient($index),
                    'songs' => $songs->map(function($song) {
                        return $this->formatSong($song);
                    })
                ];
            }
        }
        
        return response()->json(['data' => $mixes]);
    }

    /**
     * Новые релизы
     * GET /api/discover/new-releases
     */
    public function newReleases()
    {
        // Берем самые новые песни из нашей БД (первые 10)
        $localSongs = Song::with('artist')
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function($song) {
                return $this->formatSong($song);
            });

        // Получаем новые релизы из Jamendo (10 песен)
        $jamendoSongs = $this->getJamendoNewReleases(10);

        // Объединяем и перемешиваем
        $allSongs = $localSongs->merge($jamendoSongs)->shuffle()->take(20);

        return response()->json([
            'data' => $allSongs->values()
        ]);
    }

    /**
     * Рекомендации для вас
     * GET /api/discover/for-you
     */
    public function forYou()
    {
        // Исключаем новые релизы (первые 10 по дате)
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

        // Получаем популярные треки из Jamendo (10 песен)
        $jamendoSongs = $this->getJamendoTrending(10);

        // Объединяем и перемешиваем
        $allSongs = $localSongs->merge($jamendoSongs)->shuffle()->take(20);

        return response()->json([
            'data' => $allSongs->values()
        ]);
    }

    /**
     * Популярное в мире
     * GET /api/discover/around-world
     */
    public function aroundWorld()
    {
        $songs = Song::with('artist')
            ->where('status', 'approved')
            ->whereIn('country', ['US', 'UK', 'JP', 'KR', 'BR', 'FR', 'DE'])
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
     * Скрытые жемчужины
     * GET /api/discover/hidden-gems
     */
    public function hiddenGems()
    {
        $songs = Song::with('artist')
            ->where('status', 'approved')
            ->where('play_count', '<', 10000)
            ->where('rating', '>=', 4.0)
            ->inRandomOrder()
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $songs->map(function($song) {
                return $this->formatSong($song);
            })
        ]);
    }

    private function formatSong($song)
    {
        return [
            'id' => $song->id,
            'title' => $song->title,
            'artist' => $song->artist ? $song->artist->name : 'Unknown',
            'album' => $song->album ?? '',
            'duration' => $song->duration ?? '0:00',
            'cover' => $this->getCoverUrl($song->cover_url),
            'audioUrl' => $this->getAudioUrl($song),
        ];
    }

    private function getGradient($index)
    {
        $gradients = [
            'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
            'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
            'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
            'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
            'linear-gradient(135deg, #30cfd0 0%, #330867 100%)'
        ];
        
        return $gradients[$index % count($gradients)];
    }

    private function getCoverUrl($url)
    {
        if (!$url) return 'https://via.placeholder.com/300';
        if (str_starts_with($url, 'http')) return $url;
        return url('storage/' . $url);
    }

    private function getAudioUrl($song)
    {
        if (!$song->audio_url) return '';
        if (str_starts_with($song->audio_url, 'http')) return $song->audio_url;
        return url('api/stream/' . $song->id);
    }

    /**
     * Получить новые релизы из Jamendo
     */
    private function getJamendoNewReleases($limit = 10)
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
                'order' => 'releasedate_desc',
                'tags' => ''
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
                        'duration' => $this->formatDuration($track['duration'] ?? 0),
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
            Log::error('Ошибка при получении новых релизов из Jamendo: ' . $e->getMessage());
        }

        return collect([]);
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
                        'duration' => $this->formatDuration($track['duration'] ?? 0),
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
     * Форматирование длительности из секунд
     */
    private function formatDuration($seconds)
    {
        $minutes = floor($seconds / 60);
        $secs = floor($seconds % 60);
        return $minutes . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT);
    }
}
