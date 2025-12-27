<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SongController;
use App\Http\Controllers\Api\PlaylistController;
use App\Http\Controllers\Api\ArtistController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\AlbumController;
use App\Http\Controllers\Api\ChartController;
use App\Http\Controllers\Api\DiscoverController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ========== ПУБЛИЧНЫЕ МАРШРУТЫ (без авторизации) ==========
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Songs (ВАЖНО: специфичные роуты ДО динамических параметров)
Route::get('/songs', [SongController::class, 'index']);
Route::get('/songs/search', [SongController::class, 'search']);
Route::get('/songs/trending', [SongController::class, 'trending']);
Route::get('/songs/{song}', [SongController::class, 'show']);
Route::get('/stream/{id}', [SongController::class, 'stream']);

// Artists
Route::get('/artists', [ArtistController::class, 'index']);
Route::get('/artists/search', [ArtistController::class, 'search']);
Route::get('/artists/{artist}', [ArtistController::class, 'show']);

// Albums
Route::get('/albums', [AlbumController::class, 'index']);
Route::get('/albums/search', [AlbumController::class, 'search']);
Route::get('/albums/{album}', [AlbumController::class, 'show']);

// Charts
Route::get('/charts/global', [ChartController::class, 'global']);
Route::get('/charts/country', [ChartController::class, 'country']);
Route::get('/charts/genre', [ChartController::class, 'genre']);

// Discover
Route::get('/discover/daily-mixes', [DiscoverController::class, 'dailyMixes']);
Route::get('/discover/new-releases', [DiscoverController::class, 'newReleases']);
Route::get('/discover/for-you', [DiscoverController::class, 'forYou']);
Route::get('/discover/around-world', [DiscoverController::class, 'aroundWorld']);
Route::get('/discover/hidden-gems', [DiscoverController::class, 'hiddenGems']);

// ========== ЗАЩИЩЁННЫЕ МАРШРУТЫ (требуют Sanctum токен) ==========
Route::middleware('auth:sanctum')->group(function () {
    // Аутентификация
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Лайки
    Route::post('/like/song/{song}', [SongController::class, 'toggleLike']);

    // Плейлисты
    Route::get('/playlists', [PlaylistController::class, 'index']);
    Route::post('/playlists', [PlaylistController::class, 'store']);
    Route::put('/playlists/{playlist}', [PlaylistController::class, 'update']);
    Route::delete('/playlists/{playlist}', [PlaylistController::class, 'destroy']);

    // Добавление/удаление песен из плейлиста
    Route::post('/playlists/{playlist}/songs', [PlaylistController::class, 'addSong']);
    Route::delete('/playlists/{playlist}/songs/{song}', [PlaylistController::class, 'removeSong']);

    // Профиль, история, комментарии
    Route::get('/profile/stats', [ProfileController::class, 'stats']);
    Route::post('/history', [ProfileController::class, 'recordHistory']);
    Route::delete('/history', [ProfileController::class, 'clearHistory']);

    Route::get('/songs/{song}/comments', [ProfileController::class, 'getComments']);
    Route::post('/songs/{song}/comments', [ProfileController::class, 'addComment']);
});
