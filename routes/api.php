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
use App\Http\Controllers\Api\Admin\SongUploadController;
use App\Http\Controllers\Api\Admin\TrackUploadController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\SongEditController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\GenreController;
use App\Http\Controllers\Api\TrackController;
use App\Http\Controllers\Api\ArtistDashboardController;
use App\Http\Controllers\Api\SongRequestController;
use App\Http\Controllers\Api\Admin\SongModerationController;
use App\Http\Controllers\Api\Admin\AdminAnalyticsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ========== ПУБЛИЧНЫЕ МАРШРУТЫ (без авторизации) ==========
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
// 2FA (демо)
Route::post('/verify-2fa', [AuthController::class, 'verify2fa']);

// Tracks (новые маршруты для треков)
Route::get('/tracks', [TrackController::class, 'index']);
Route::get('/tracks/search', [TrackController::class, 'search']);
Route::get('/tracks/trending', [TrackController::class, 'trending']);
Route::get('/tracks/{track}', [TrackController::class, 'show']);
Route::get('/tracks/{id}/stream', [TrackController::class, 'stream']);

// Songs (ВАЖНО: специфичные роуты ДО динамических параметров) - для обратной совместимости
Route::get('/songs', [SongController::class, 'index']);
Route::get('/songs/search', [SongController::class, 'search']);
Route::get('/songs/trending', [SongController::class, 'trending']);
Route::get('/songs/{song}', [SongController::class, 'show']);
Route::get('/stream/{id}', [SongController::class, 'stream']);

// Artists
Route::get('/artists', [ArtistController::class, 'index']);
Route::get('/artists/search', [ArtistController::class, 'search']);
Route::get('/artists/{artist}', [ArtistController::class, 'show']);

// Genres
Route::get('/genres', [GenreController::class, 'index']);

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
    Route::post('/user/request-artist-status', [AuthController::class, 'requestArtistStatus']);
    Route::patch('/user/toggle-2fa', [AuthController::class, 'toggle2fa']);

    // Лайки
    Route::post('/like/song/{song}', [SongController::class, 'toggleLike']);

    // Плейлисты
    Route::get('/playlists', [PlaylistController::class, 'index']);
    Route::get('/playlists/{playlist}', [PlaylistController::class, 'show']);
    Route::post('/playlists', [PlaylistController::class, 'store']);
    Route::put('/playlists/{playlist}', [PlaylistController::class, 'update']);
    Route::delete('/playlists/{playlist}', [PlaylistController::class, 'destroy']);

    // Добавление/удаление песен из плейлиста
    Route::post('/playlists/{playlist}/songs', [PlaylistController::class, 'addSong']);
    Route::delete('/playlists/{playlist}/songs/{song}', [PlaylistController::class, 'removeSong']);
    Route::post('/playlists/{playlist}/external-tracks', [PlaylistController::class, 'addExternalSong']);
    Route::delete('/playlists/{playlist}/external-tracks/{externalId}', [PlaylistController::class, 'removeExternalSong']);

    // Профиль, история, комментарии
    Route::get('/profile/stats', [ProfileController::class, 'stats']);
    Route::get('/user/liked-songs', [ProfileController::class, 'getLikedSongs']);
    Route::get('/user/history', [ProfileController::class, 'getHistory']);
    Route::post('/user/history', [ProfileController::class, 'recordHistory']);
    Route::post('/user/history/external', [ProfileController::class, 'recordExternalHistory']);
    Route::delete('/history', [ProfileController::class, 'clearHistory']);

    Route::get('/songs/{song}/comments', [ProfileController::class, 'getComments']);
    Route::post('/songs/{song}/comments', [ProfileController::class, 'addComment']);

    // Статистика артиста
    Route::prefix('artist/dashboard')->group(function () {
        Route::get('/stats', [ArtistDashboardController::class, 'getStats']);
        Route::get('/songs', [ArtistDashboardController::class, 'getSongs']);
        Route::get('/analytics', [ArtistDashboardController::class, 'getAnalytics']);
        Route::get('/info', [ArtistDashboardController::class, 'getInfo']);
    });

    // Заявки на модерацию треков (для артистов)
    Route::post('/song-requests', [SongRequestController::class, 'store']);
    Route::get('/song-requests', [SongRequestController::class, 'index']);

    // Админские роуты (проверка роли в контроллере)
    Route::prefix('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::patch('/users/{id}/ban', [AdminController::class, 'banUser']);
        Route::patch('/users/{id}', [AdminController::class, 'updateUser']);
        Route::get('/dashboard/stats', [AdminDashboardController::class, 'getStats']);
        
        // Запросы артистов
        Route::get('/artist-requests', [AdminController::class, 'getArtistRequests']);
        Route::post('/artist-requests/{id}/approve', [AdminController::class, 'approveArtistRequest']);
        Route::post('/artist-requests/{id}/reject', [AdminController::class, 'rejectArtistRequest']);
        
        // Артисты (пользователи с role='artist')
        Route::get('/artists-list', [AdminController::class, 'getArtistsList']);
        Route::patch('/artists/{id}', [AdminController::class, 'updateArtist']);
        Route::patch('/artists/{id}/ban', [AdminController::class, 'banArtist']);
        
        // Загрузка треков (новые маршруты)
        Route::post('/tracks/upload', [TrackUploadController::class, 'store']);
        Route::get('/tracks/artists', [TrackUploadController::class, 'getArtists']);
        
        // Загрузка песен (для обратной совместимости)
        Route::post('/songs/upload', [SongUploadController::class, 'store']);
        Route::get('/artists', [SongUploadController::class, 'getArtists']);
        
        Route::put('/songs/{song}', [SongEditController::class, 'update']);
        Route::delete('/songs/{song}', [SongEditController::class, 'destroy']);
        
        // Модерация треков
        Route::get('/song-requests', [SongModerationController::class, 'index']);
        Route::post('/song-requests/{id}/approve', [SongModerationController::class, 'approve']);
        Route::post('/song-requests/{id}/reject', [SongModerationController::class, 'reject']);
        
        // Аналитика
        Route::get('/analytics', [AdminAnalyticsController::class, 'index']);
    });

    // Жанры (CRUD для админа)
    Route::post('/genres', [GenreController::class, 'store']);
    Route::put('/genres/{id}', [GenreController::class, 'update']);
    Route::delete('/genres/{id}', [GenreController::class, 'destroy']);

    // Артисты (создание/удаление только для админа)
    Route::post('/artists', [ArtistController::class, 'store']);
    Route::delete('/artists/{artist}', [ArtistController::class, 'destroy']);

    // Подписки на артистов (Любимые артисты)
    Route::post('/artists/{artist}/follow', [ArtistController::class, 'toggleFollow']);
    Route::get('/user/followed-artists', function (Request $request) {
        $artists = $request->user()
            ->followedArtists()
            ->select('artists.id', 'artists.name', 'artists.cover_url', 'artists.genre')
            ->orderBy('artists.name')
            ->get()
            ->map(function ($a) {
                $cover = $a->cover_url;
                if ($cover && !str_starts_with($cover, 'http')) {
                    $cover = url('storage/' . ltrim($cover, '/'));
                }
                return [
                    'id' => $a->id,
                    'name' => $a->name,
                    'cover' => $cover ?: 'https://via.placeholder.com/300',
                    'genre' => $a->genre,
                ];
            });

        return response()->json(['data' => $artists->values()]);
    });
});
