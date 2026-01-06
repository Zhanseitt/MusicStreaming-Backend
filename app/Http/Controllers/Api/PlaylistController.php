<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Playlist;
use Illuminate\Http\Request;

class PlaylistController extends Controller
{
    private function isLikedPlaylist(Playlist $playlist): bool
    {
        // В фронте используются оба названия как "liked playlist"
        return in_array($playlist->name, ['Понравившиеся', 'Любимые'], true);
    }

    // Получить все плейлисты пользователя
    public function index(Request $request)
    {
        // Возвращаем плейлисты + список ID песен внутри них (для фронтенда)
        $playlists = $request->user()->playlists()->with('songs:id')->get()->map(function($playlist) {
            // Загружаем обычные песни - возвращаем ID
            $songIds = $playlist->songs->pluck('id')->toArray();
            
            // Загружаем external tracks (Jamendo) - возвращаем объекты с полными данными
            $externalTracks = \DB::table('playlist_external_tracks')
                ->where('playlist_id', $playlist->id)
                ->get()
                ->map(function($track) {
                    // Возвращаем объект с данными внешнего трека
                    // ID формируем с префиксом "jamendo_" для фронтенда
                    return [
                        'id' => 'jamendo_' . $track->external_id, // ID с префиксом для фронтенда
                        'title' => $track->title,
                        'artist' => $track->artist,
                        'cover' => $track->cover,
                        'audioUrl' => $track->audio_url,
                        'source' => $track->source ?? 'jamendo',
                        'shareurl' => $track->shareurl,
                        'tags' => $track->tags,
                        'is_external' => true, // Флаг для фронтенда
                    ];
                })
                ->toArray();
            
            // Объединяем обычные ID (числа) и объекты внешних треков
            $allSongs = array_merge($songIds, $externalTracks);
            
            return [
                'id' => $playlist->id,
                'name' => $playlist->name,
                'color' => $playlist->color ?? 'bg-indigo-600',
                'songs' => $allSongs, // Массив: [1, 5, 12, {id: "jamendo_123", title: "...", ...}, ...]
                'is_collaborative' => $playlist->is_collaborative ?? false,
                'created_at' => $playlist->created_at,
            ];
        });
        
        return response()->json(['data' => $playlists]);
    }

    // Получить один плейлист
    public function show(Request $request, Playlist $playlist)
    {
        if ($request->user()->id !== $playlist->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        
        $playlist->load('songs:id');
        
        // Загружаем обычные песни - возвращаем ID
        $songIds = $playlist->songs->pluck('id')->toArray();
        
        // Загружаем external tracks (Jamendo) - возвращаем объекты с полными данными
        $externalTracks = \DB::table('playlist_external_tracks')
            ->where('playlist_id', $playlist->id)
            ->get()
            ->map(function($track) {
                // Возвращаем объект с данными внешнего трека
                return [
                    // Должно совпадать с index(): фронтенд ожидает id вида "jamendo_123"
                    'id' => 'jamendo_' . $track->external_id,
                    'title' => $track->title,
                    'artist' => $track->artist,
                    'cover' => $track->cover,
                    'audioUrl' => $track->audio_url,
                    'source' => $track->source ?? 'jamendo',
                    'shareurl' => $track->shareurl,
                    'tags' => $track->tags,
                    'is_external' => true, // Флаг для фронтенда
                ];
            })
            ->toArray();
        
        // Объединяем обычные ID (числа) и объекты внешних треков
        $allSongs = array_merge($songIds, $externalTracks);
        
        return response()->json([
            'id' => $playlist->id,
            'name' => $playlist->name,
            'color' => $playlist->color ?? 'bg-indigo-600',
            'songs' => $allSongs, // Массив: [1, 5, 12, {id: "jamendo_123", title: "...", ...}, ...]
            'is_collaborative' => $playlist->is_collaborative ?? false,
            'created_at' => $playlist->created_at,
        ]);
    }

    // Удалить внешний трек (Jamendo) из плейлиста
    // DELETE /api/playlists/{playlist}/external-tracks/{externalId}
    public function removeExternalSong(Request $request, Playlist $playlist, string $externalId)
    {
        if ($request->user()->id !== $playlist->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Поддерживаем оба формата: "jamendo_123" и "123"
        $cleanExternalId = str_starts_with($externalId, 'jamendo_')
            ? str_replace('jamendo_', '', $externalId)
            : $externalId;

        \DB::table('playlist_external_tracks')
            ->where('playlist_id', $playlist->id)
            ->where('external_id', $cleanExternalId)
            ->delete();

        return response()->json(['message' => 'External track removed']);
    }

    // Создать плейлист
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:255',
        ]);

        // Доступные цвета для случайного выбора
        $availableColors = [
            'bg-red-600',
            'bg-blue-600',
            'bg-green-600',
            'bg-yellow-600',
            'bg-indigo-600',
            'bg-pink-600'
        ];

        // Если цвет не указан, выбираем случайный
        $color = $validated['color'] ?? $availableColors[array_rand($availableColors)];

        $playlistData = [
            'name' => $validated['name'],
            'color' => $color,
        ];
        
        $playlist = $request->user()->playlists()->create($playlistData);

        // Возвращаем структуру, которую ждет фронт
        return response()->json([
            'success' => true,
            'playlist' => [
                'id' => $playlist->id,
                'name' => $playlist->name,
                'color' => $playlist->color ?? 'bg-indigo-600',
                'songs' => [],
                'is_collaborative' => false
            ]
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

        // Если это "liked" плейлист — синхронизируем user_liked_songs для корректных лайк-счетчиков
        if ($this->isLikedPlaylist($playlist)) {
            $request->user()->likedSongs()->syncWithoutDetaching([$request->song_id]);
        }

        return response()->json(['message' => 'Song added']);
    }

    // Удалить песню из плейлиста
    public function removeSong(Request $request, Playlist $playlist, $songId)
    {
        if ($request->user()->id !== $playlist->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $playlist->songs()->detach($songId);

        // Если это "liked" плейлист — убираем запись из user_liked_songs
        if ($this->isLikedPlaylist($playlist)) {
            $request->user()->likedSongs()->detach($songId);
        }

        return response()->json(['message' => 'Song removed']);
    }

    // Добавить внешний трек (Jamendo) в плейлист
    public function addExternalSong(Request $request, Playlist $playlist)
    {
        if ($request->user()->id !== $playlist->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'artist' => 'required|string|max:255',
            'cover' => 'nullable|string',
            'audioUrl' => 'required|url',
            'source' => 'required|string|in:jamendo',
            'external_id' => 'required|string',
            'shareurl' => 'nullable|url',
            'tags' => 'nullable|string',
        ]);

        // Проверяем, не добавлен ли уже этот трек в плейлист
        $exists = \DB::table('playlist_external_tracks')
            ->where('playlist_id', $playlist->id)
            ->where('external_id', $validated['external_id'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Track already in playlist'], 409);
        }

        \DB::table('playlist_external_tracks')->insert([
            'playlist_id' => $playlist->id,
            'external_id' => $validated['external_id'],
            'title' => $validated['title'],
            'artist' => $validated['artist'],
            'cover' => $validated['cover'] ?? null,
            'audio_url' => $validated['audioUrl'],
            'source' => $validated['source'],
            'shareurl' => $validated['shareurl'] ?? null,
            'tags' => $validated['tags'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'External track added'], 201);
    }
}