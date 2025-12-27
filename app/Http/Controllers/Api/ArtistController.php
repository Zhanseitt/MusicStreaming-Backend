<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use Illuminate\Http\Request;

class ArtistController extends Controller
{
    /**
     * Список всех артистов
     * GET /api/artists
     */
    public function index()
    {
        $artists = Artist::withCount('songs')->latest()->get();

        return response()->json([
            'data' => $artists->map(function($artist) {
                return $this->formatArtist($artist);
            })
        ]);
    }

    /**
     * Детальная информация об артисте
     * GET /api/artists/{artist}
     */
    public function show(Artist $artist)
    {
        $artist->load('songs');

        return response()->json([
            'id' => $artist->id,
            'name' => $artist->name,
            'cover' => $this->getCoverUrl($artist->cover_url),
            'artist' => $artist->genre ?? 'Artist',
            'bio' => $artist->bio ?? '',
            'songs_count' => $artist->songs->count(),
            'songs' => $artist->songs->map(function($song) {
                return [
                    'id' => $song->id,
                    'title' => $song->title,
                    'album' => $song->album,
                    'duration' => $song->duration,
                    'cover' => $this->getCoverUrl($song->cover_url),
                    'audioUrl' => $this->getAudioUrl($song),
                ];
            })
        ]);
    }

    /**
     * Поиск артистов
     * GET /api/artists/search?q=query
     */
    public function search(Request $request)
    {
        $query = $request->input('q');

        if (!$query) {
            return response()->json(['data' => []]);
        }

        $artists = Artist::where('name', 'LIKE', "%{$query}%")
            ->withCount('songs')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $artists->map(function($artist) {
                return $this->formatArtist($artist);
            })
        ]);
    }

    private function formatArtist($artist)
    {
        return [
            'name' => $artist->name,
            'artist' => $artist->genre ?? 'Artist',
            'cover' => $this->getCoverUrl($artist->cover_url),
        ];
    }

    private function getCoverUrl($url)
    {
        if (!$url) {
            return 'https://via.placeholder.com/300';
        }
        
        if (str_starts_with($url, 'http')) {
            return $url;
        }
        
        return url('storage/' . $url);
    }

    private function getAudioUrl($song)
    {
        $audioUrl = $song->audio_url;
        
        if (!$audioUrl) {
            return '';
        }
        
        if (str_starts_with($audioUrl, 'http')) {
            return $audioUrl;
        }
        
        return url('api/stream/' . $song->id);
    }
}
