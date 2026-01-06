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
        $artists = Artist::withCount(['songs', 'followers'])->latest()->get();

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
        $artist->loadCount('followers');

        return response()->json([
            'id' => $artist->id,
            'name' => $artist->name,
            'cover' => $this->getCoverUrl($artist->cover_url),
            'genre' => $artist->genre ?? null,
            'bio' => $artist->bio ?? '',
            'songs_count' => $artist->songs->count(),
            'followers' => (int) ($artist->followers_count ?? 0),
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
            ->withCount(['songs', 'followers'])
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
            'id' => $artist->id,
            'name' => $artist->name,
            'genre' => $artist->genre ?? null,
            'cover' => $this->getCoverUrl($artist->cover_url),
            'songs_count' => (int) ($artist->songs_count ?? 0),
            'followers' => (int) ($artist->followers_count ?? 0),
        ];
    }

    /**
     * Подписаться/отписаться от артиста (добавить/убрать из "Любимых артистов")
     * POST /api/artists/{artist}/follow
     */
    public function toggleFollow(Request $request, Artist $artist)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Не авторизован'], 401);
        }

        $artist->followers()->toggle($user->id);

        $isFollowed = $artist->followers()->where('users.id', $user->id)->exists();
        $followersCount = $artist->followers()->count();

        return response()->json([
            'success' => true,
            'followed' => $isFollowed,
            'followers' => $followersCount,
        ]);
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

    /**
     * Создать нового артиста (только для админа)
     * POST /api/artists
     */
    public function store(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'bio' => 'nullable|string',
            'cover_url' => 'nullable|string',
        ]);

        $artist = Artist::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Артист успешно создан',
            'data' => $this->formatArtist($artist)
        ], 201);
    }

    /**
     * Удалить артиста (только для админа)
     * DELETE /api/artists/{id}
     */
    public function destroy(Request $request, Artist $artist)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $artist->delete();

        return response()->json([
            'success' => true,
            'message' => 'Артист успешно удален'
        ]);
    }
}
