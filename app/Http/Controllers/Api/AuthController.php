<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role'     => 'user',   // чтобы фронт всегда получил role
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'      => $user->id,
                'name'    => $user->name,
                'email'   => $user->email,
                'role'    => $user->role,
                // Дополнительно под фронт:
                'avatar_url' => $user->avatar_url ?? null,
                'plan'       => $user->plan ?? 'free',
                'is_artist_requested' => $user->is_artist_requested ?? false,
            ],
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Неверные учетные данные.'],
            ]);
        }

        // 2FA (демо): если включено — НЕ выдаём токен, а просим код.
        if ((bool)($user->is_2fa_enabled ?? false) === true) {
            return response()->json([
                'status' => 'requires_2fa',
                'email' => $user->email,
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'role'       => $user->role,
                'avatar_url' => $user->avatar_url ?? null,
                'plan'       => $user->plan ?? 'free',
                'is_artist_requested' => $user->is_artist_requested ?? false,
                'is_2fa_enabled' => (bool)($user->is_2fa_enabled ?? false),
            ],
        ]);
    }

    /**
     * Подтверждение 2FA (демо)
     * POST /api/verify-2fa
     *
     * Принимает email + code. Для демо код фиксированный: 123456.
     */
    public function verify2fa(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Пользователь не найден.'],
            ]);
        }

        // Если у пользователя 2FA выключен — не даём подтверждать код.
        if (!(bool)($user->is_2fa_enabled ?? false)) {
            return response()->json([
                'message' => '2FA не включен для этого пользователя.',
            ], 422);
        }

        if ($validated['code'] !== '123456') {
            throw ValidationException::withMessages([
                'code' => ['Неверный код.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'role'       => $user->role,
                'avatar_url' => $user->avatar_url ?? null,
                'plan'       => $user->plan ?? 'free',
                'is_artist_requested' => $user->is_artist_requested ?? false,
                'is_2fa_enabled' => (bool)($user->is_2fa_enabled ?? false),
            ],
        ]);
    }

    /**
     * Переключение флага 2FA для текущего пользователя
     * PATCH /api/user/toggle-2fa
     */
    public function toggle2fa(Request $request)
    {
        $user = $request->user();
        $user->is_2fa_enabled = !(bool)($user->is_2fa_enabled ?? false);
        $user->save();

        return response()->json([
            'success' => true,
            'user' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'role'       => $user->role,
                'avatar_url' => $user->avatar_url ?? null,
                'plan'       => $user->plan ?? 'free',
                'is_artist_requested' => $user->is_artist_requested ?? false,
                'is_2fa_enabled' => (bool)($user->is_2fa_enabled ?? false),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Вы вышли']);
    }

    public function user(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'role'       => $user->role,
            'avatar_url' => $user->avatar_url ?? null,
            'plan'       => $user->plan ?? 'free',
            'is_artist_requested' => $user->is_artist_requested ?? false,
            'is_2fa_enabled' => (bool)($user->is_2fa_enabled ?? false),
        ]);
    }

    /**
     * Запрос статуса артиста
     * POST /api/user/request-artist-status
     */
    public function requestArtistStatus(Request $request)
    {
        $user = $request->user();

        // Проверяем, что пользователь еще не артист и не отправил запрос
        if ($user->role === 'artist') {
            return response()->json([
                'success' => false,
                'message' => 'Вы уже являетесь артистом',
            ], 400);
        }

        if ($user->is_artist_requested) {
            return response()->json([
                'success' => false,
                'message' => 'Вы уже отправили заявку на статус артиста',
            ], 400);
        }

        // Устанавливаем флаг запроса
        $user->is_artist_requested = true;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Заявка на статус артиста успешно отправлена',
            'user' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'role'       => $user->role,
                'avatar_url' => $user->avatar_url ?? null,
                'plan'       => $user->plan ?? 'free',
                'is_artist_requested' => $user->is_artist_requested,
            ],
        ]);
    }
}
