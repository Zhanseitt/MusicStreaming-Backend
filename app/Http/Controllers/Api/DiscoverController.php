<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Song;
use Illuminate\Http\Request;

class DiscoverController extends Controller
{
    /**
     * Daily Mixes
     * GET /api/discover/daily-mixes
     */
    public function dailyMixes()
    {
        $genres = ['pop', 'rock', 'hip-hop', 'electronic', 'jazz', 'classical'];
        
        $mixes = [];
        foreach ($genres as $index => $genre) {
            $songs = Song::with('artist')
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
        $songs = Song::with('artist')
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $songs->map(function($song) {
                return $this->formatSong($song);
            })
        ]);
    }

    /**
     * Рекомендации для вас
     * GET /api/discover/for-you
     */
    public function forYou()
    {
        $songs = Song::with('artist')
            ->inRandomOrder()
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $songs->map(function($song) {
                return $this->formatSong($song);
            })
        ]);
    }

    /**
     * Популярное в мире
     * GET /api/discover/around-world
     */
    public function aroundWorld()
    {
        $songs = Song::with('artist')
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
}
