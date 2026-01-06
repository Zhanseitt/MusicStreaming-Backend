<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Song;
use Illuminate\Http\Request;

class AlbumController extends Controller
{
    /**
     * Список альбомов
     * GET /api/albums
     */
    public function index()
    {
        $albums = Song::select('album', 'artist_id', 'cover_url')
            ->where('status', 'approved')
            ->whereNotNull('album')
            ->with('artist')
            ->groupBy('album', 'artist_id', 'cover_url')
            ->latest()
            ->get();

        return response()->json([
            'data' => $albums->map(function($song) {
                return [
                    'id' => $song->id,
                    'title' => $song->album,
                    'artist' => $song->artist ? $song->artist->name : 'Unknown',
                    'cover' => $this->getCoverUrl($song->cover_url),
                    'album' => $song->album,
                ];
            })
        ]);
    }

    /**
     * Детали альбома
     * GET /api/albums/{album}
     */
    public function show($albumName)
    {
        $songs = Song::where('album', $albumName)
            ->where('status', 'approved')
            ->with('artist')
            ->get();

        if ($songs->isEmpty()) {
            return response()->json(['error' => 'Album not found'], 404);
        }

        $firstSong = $songs->first();

        return response()->json([
            'album' => $albumName,
            'artist' => $firstSong->artist ? $firstSong->artist->name : 'Unknown',
            'cover' => $this->getCoverUrl($firstSong->cover_url),
            'songs' => $songs->map(function($song) {
                return [
                    'id' => $song->id,
                    'title' => $song->title,
                    'artist' => $song->artist ? $song->artist->name : 'Unknown',
                    'duration' => $song->duration,
                    'cover' => $this->getCoverUrl($song->cover_url),
                    'audioUrl' => $this->getAudioUrl($song),
                ];
            })
        ]);
    }

    /**
     * Поиск альбомов
     * GET /api/albums/search?q=query
     */
    public function search(Request $request)
    {
        $query = $request->input('q');

        if (!$query) {
            return response()->json(['data' => []]);
        }

        $albums = Song::where('album', 'LIKE', "%{$query}%")
            ->where('status', 'approved')
            ->whereNotNull('album')
            ->with('artist')
            ->groupBy('album', 'artist_id', 'cover_url', 'id')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $albums->map(function($song) {
                return [
                    'id' => $song->id,
                    'title' => $song->album,
                    'artist' => $song->artist ? $song->artist->name : 'Unknown',
                    'cover' => $this->getCoverUrl($song->cover_url),
                    'album' => $song->album,
                ];
            })
        ]);
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
