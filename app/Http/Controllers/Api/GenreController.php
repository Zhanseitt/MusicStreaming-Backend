<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use Illuminate\Http\Request;
use App\Models\Song;

class GenreController extends Controller
{
    /**
     * Получить список всех жанров
     * GET /api/genres
     */
    public function index()
    {
        $genres = Genre::orderBy('name')->get();
        
        return response()->json([
            'data' => $genres->map(function($genre) {
                return [
                    'id' => $genre->id,
                    'name' => $genre->name,
                    'created_at' => $genre->created_at,
                ];
            })
        ]);
    }

    /**
     * Создать новый жанр (только для админа)
     * POST /api/genres
     */
    public function store(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:genres,name',
        ]);

        $genre = Genre::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Жанр успешно создан',
            'data' => [
                'id' => $genre->id,
                'name' => $genre->name,
            ]
        ], 201);
    }

    /**
     * Обновить жанр (только для админа)
     * PUT /api/genres/{id}
     */
    public function update(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $genre = Genre::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:genres,name,' . $id,
        ]);

        $genre->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Жанр успешно обновлен',
            'data' => [
                'id' => $genre->id,
                'name' => $genre->name,
            ]
        ]);
    }

    /**
     * Удалить жанр (только для админа)
     * DELETE /api/genres/{id}
     */
    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $genre = Genre::findOrFail($id);

        // ВАЖНО: при удалении жанра каскадно "отвязываем" его от песен/треков
        // (даже если в БД нет FK с ON DELETE CASCADE — это гарантирует консистентность).
        try {
            $genre->songs()->detach();
        } catch (\Throwable $e) {
            // no-op
        }
        try {
            $genre->tracks()->detach();
        } catch (\Throwable $e) {
            // no-op
        }

        // Если мы используем songs.genre_id как "основной жанр" — очищаем его
        try {
            Song::where('genre_id', $genre->id)->update(['genre_id' => null]);
        } catch (\Throwable $e) {
            // no-op
        }

        $genre->delete();

        return response()->json([
            'success' => true,
            'message' => 'Жанр успешно удален'
        ]);
    }
}
