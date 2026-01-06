<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Song;
use App\Models\Artist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /**
     * Получить статистику для админ-панели
     * GET /api/admin/stats
     */
    public function stats(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        return response()->json([
            'total_users' => User::where('role', '!=', 'admin')->count(),
            'total_songs' => Song::count(),
            'total_artists' => Artist::count(),
            'active_users' => User::where('updated_at', '>=', now()->subDay())
                ->where('role', '!=', 'admin')
                ->count(),
        ]);
    }

    /**
     * Получить список всех пользователей с пагинацией
     * GET /api/admin/users?page=1&per_page=20
     */
    public function users(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $perPage = $request->input('per_page', 20);
        $users = User::select('id', 'name', 'email', 'role', 'is_banned', 'created_at')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => $users->items(),
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
            'per_page' => $users->perPage(),
            'total' => $users->total(),
        ]);
    }

    /**
     * Забанить/разбанить пользователя
     * PATCH /api/admin/users/{id}/ban
     */
    public function banUser(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $user = User::findOrFail($id);
        
        // Нельзя забанить админа
        if ($user->role === 'admin') {
            return response()->json(['error' => 'Нельзя забанить администратора'], 403);
        }

        $user->is_banned = !$user->is_banned;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => $user->is_banned ? 'Пользователь забанен' : 'Пользователь разбанен',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_banned' => $user->is_banned,
            ]
        ]);
    }

    /**
     * Обновить пользователя (имя/email/роль)
     * PATCH /api/admin/users/{id}
     */
    public function updateUser(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(['user', 'artist', 'admin'])],
        ]);

        // Нельзя забанить админа мы уже защищаем в banUser; тут защитим от случайного снятия своего админства
        if ((int)$request->user()->id === (int)$user->id && $validated['role'] !== 'admin') {
            return response()->json(['error' => 'Нельзя изменить роль самого себя на не-admin'], 422);
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];
        if ($validated['role'] === 'artist') {
            $user->is_artist_requested = false;
        }
        $user->save();

        // Если делаем артистом — гарантируем запись в artists
        if ($validated['role'] === 'artist') {
            Artist::firstOrCreate(
                ['name' => $user->name],
                ['bio' => null, 'cover_url' => null, 'genre' => null]
            );
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_banned' => (bool)$user->is_banned,
            ],
        ]);
    }

    /**
     * Получить список запросов на статус артиста
     * GET /api/admin/artist-requests
     */
    public function getArtistRequests(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $requests = User::where('is_artist_requested', true)
            ->where('role', '!=', 'artist')
            ->select('id', 'name', 'email', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $requests
        ]);
    }

    /**
     * Одобрить запрос на статус артиста
     * POST /api/admin/artist-requests/{id}/approve
     */
    public function approveArtistRequest(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $user = User::findOrFail($id);

        if ($user->role === 'artist') {
            return response()->json(['error' => 'Пользователь уже является артистом'], 400);
        }

        return DB::transaction(function () use ($user) {
            // Создаем запись в таблице artists, если её еще нет.
            // Важно: делаем это ДО смены роли, чтобы при ошибке не было "частичного успеха".
            Artist::firstOrCreate(
                ['name' => $user->name],
                ['bio' => null, 'cover_url' => null, 'genre' => null]
            );

            // Меняем роль на artist
            $user->role = 'artist';
            $user->is_artist_requested = false;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Запрос одобрен. Пользователь стал артистом.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ]
            ]);
        });
    }

    /**
     * Отклонить запрос на статус артиста
     * POST /api/admin/artist-requests/{id}/reject
     */
    public function rejectArtistRequest(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $user = User::findOrFail($id);

        $user->is_artist_requested = false;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Запрос отклонен',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]
        ]);
    }

    /**
     * Получить список артистов (пользователей с role='artist') с количеством треков
     * GET /api/admin/artists-list
     */
    public function getArtistsList(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $artists = User::where('role', 'artist')
            ->select('id', 'name', 'email', 'is_banned', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($user) {
                // Находим артиста по имени в таблице artists
                $artistRecord = Artist::where('name', $user->name)->first();
                
                // Подсчитываем количество треков (songs)
                $songsCount = 0;
                if ($artistRecord) {
                    $songsCount = Song::where('artist_id', $artistRecord->id)->count();
                }

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_banned' => $user->is_banned,
                    'songs_count' => $songsCount,
                    'created_at' => $user->created_at,
                ];
            });

        return response()->json([
            'data' => $artists
        ]);
    }

    /**
     * Обновить данные артиста
     * PATCH /api/admin/artists/{id}
     */
    public function updateArtist(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $user = User::findOrFail($id);

        if ($user->role !== 'artist') {
            return response()->json(['error' => 'Пользователь не является артистом'], 400);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255',
        ]);

        $oldName = $user->name;

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }

        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }

        $user->save();

        // Обновляем имя в таблице artists
        if (isset($validated['name']) && $oldName !== $validated['name']) {
            $artistRecord = Artist::where('name', $oldName)->first();
            if ($artistRecord) {
                $artistRecord->name = $validated['name'];
                $artistRecord->save();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Данные артиста обновлены',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]
        ]);
    }

    /**
     * Забанить/разбанить артиста
     * PATCH /api/admin/artists/{id}/ban
     */
    public function banArtist(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $user = User::findOrFail($id);

        if ($user->role !== 'artist') {
            return response()->json(['error' => 'Пользователь не является артистом'], 400);
        }

        $validated = $request->validate([
            'ban_reason' => 'nullable|string|max:500',
        ]);

        $user->is_banned = !$user->is_banned;
        
        if ($user->is_banned && isset($validated['ban_reason'])) {
            $user->ban_reason = $validated['ban_reason'];
        } else {
            $user->ban_reason = null;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => $user->is_banned ? 'Артист забанен' : 'Артист разбанен',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_banned' => $user->is_banned,
                'ban_reason' => $user->ban_reason,
            ]
        ]);
    }
}
