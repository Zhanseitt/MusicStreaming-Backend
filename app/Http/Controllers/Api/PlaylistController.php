<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Playlist;
use Illuminate\Http\Request;

class PlaylistController extends Controller
{
    // Получить все плейлисты пользователя
    public function index(Request $request)
    {
        // Возвращаем плейлисты + список ID песен внутри них (для фронтенда)
        return $request->user()->playlists()->with('songs:id')->get()->map(function($playlist) {
            return [
                'id' => $playlist->id,
                'name' => $playlist->name,
                'color' => $playlist->color,
                'songs' => $playlist->songs->pluck('id'), // Массив ID песен: [1, 5, 12]
                'created_at' => $playlist->created_at,
            ];
        });
    }

    // Создать плейлист
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string',
        ]);

        $playlist = $request->user()->playlists()->create($validated);

        // Возвращаем структуру, которую ждет фронт
        return response()->json([
            'id' => $playlist->id,
            'name' => $playlist->name,
            'color' => $playlist->color,
            'songs' => [] // Новый плейлист пуст
        ], 201);
    }

    // Обновить (имя/цвет)
    public function update(Request $request, Playlist $playlist)
    {
        if ($request->user()->id !== $playlist->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        
        $playlist->update($request->only(['name', 'color']));
        return response()->json($playlist);
    }

    // Удалить плейлист
    public function destroy(Request $request, Playlist $playlist)
    {
        if ($request->user()->id !== $playlist->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $playlist->delete();
        return response()->noContent();
    }

    // Добавить песню в плейлист
    public function addSong(Request $request, Playlist $playlist)
    {
        if ($request->user()->id !== $playlist->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate(['song_id' => 'required|exists:songs,id']);

        // syncWithoutDetaching добавляет, если такой связи еще нет
        $playlist->songs()->syncWithoutDetaching([$request->song_id]);

        return response()->json(['message' => 'Song added']);
    }

    // Удалить песню из плейлиста
    public function removeSong(Request $request, Playlist $playlist, $songId)
    {
        if ($request->user()->id !== $playlist->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $playlist->songs()->detach($songId);

        return response()->json(['message' => 'Song removed']);
    }
}