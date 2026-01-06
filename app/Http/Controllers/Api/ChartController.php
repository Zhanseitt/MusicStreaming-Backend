<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Song;
use App\Models\Genre;
use Illuminate\Http\Request;

class ChartController extends Controller
{
    /**
     * Глобальные чарты
     * GET /api/charts/global
     */
    public function global()
    {
        $songs = Song::with('artist')
            ->where('status', 'approved')
            ->orderBy('play_count', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $songs->map(function($song, $index) {
                return array_merge($this->formatSong($song), [
                    'rank' => $index + 1,
                    'listeners' => $this->formatListeners($song->play_count),
                    'trend' => $this->getTrend()
                ]);
            })
        ]);
    }

    /**
     * Чарты по стране
     * GET /api/charts/country?country=US
     */
    public function country(Request $request)
    {
        $country = $request->input('country', 'US');

        $songs = Song::with('artist')
            ->where('status', 'approved')
            ->where('country', $country)
            ->orderBy('play_count', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $songs->map(function($song, $index) {
                return array_merge($this->formatSong($song), [
                    'rank' => $index + 1,
                    'listeners' => $this->formatListeners($song->play_count),
                    'trend' => $this->getTrend()
                ]);
            })
        ]);
    }

    /**
     * Чарты по жанру
     * GET /api/charts/genre?genre=pop
     */
    public function genre(Request $request)
    {
        $genre = $request->input('genre', 'pop');

        $songsQuery = Song::with('artist')
            ->where('status', 'approved')
            ->orderBy('play_count', 'desc')
            ->limit(50);

        // Новый источник истины: связь songs<->genres
        $genreModel = Genre::whereRaw('LOWER(name) = ?', [mb_strtolower((string) $genre)])->first();
        if ($genreModel) {
            $songsQuery->whereHas('genres', function ($q) use ($genreModel) {
                $q->where('genres.id', $genreModel->id);
            });
        } else {
            // Fallback на старое поле songs.genre
            $songsQuery->where('genre', $genre);
        }

        $songs = $songsQuery->get();

        return response()->json([
            'data' => $songs->map(function($song, $index) {
                return array_merge($this->formatSong($song), [
                    'rank' => $index + 1,
                    'listeners' => $this->formatListeners($song->play_count),
                    'trend' => $this->getTrend()
                ]);
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

    private function formatListeners($count)
    {
        if ($count >= 1000000) {
            return round($count / 1000000, 1) . 'M';
        } elseif ($count >= 1000) {
            return round($count / 1000, 1) . 'k';
        }
        return $count;
    }

    private function getTrend()
    {
        return ['up', 'down', 'same'][rand(0, 2)];
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
