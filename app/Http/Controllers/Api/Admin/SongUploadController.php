<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Song;
use App\Models\Artist;
use App\Models\Genre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SongUploadController extends Controller
{
    /**
     * Загрузка новой песни админом
     * POST /api/admin/songs/upload
     */
    public function store(Request $request)
    {
        // Логирование всех входящих данных для отладки
        $allFiles = $request->allFiles();
        Log::info('SongUploadController::store - Входящие данные', [
            'all' => $request->all(),
            'files_count' => count($allFiles),
            'file_info' => array_map(function($file) {
                return $file ? [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                    'is_valid' => $file->isValid(),
                    'extension' => $file->getClientOriginalExtension()
                ] : null;
            }, $allFiles),
            'headers' => $request->headers->all(),
            'user_id' => $request->user()?->id,
            'user_role' => $request->user()?->role,
        ]);

        // Проверяем, что пользователь - админ
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        // Валидация
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'artist_id' => 'required|integer|exists:artists,id',
            'album' => 'nullable|string|max:255',
            'duration' => 'nullable|string',
            'genre_id' => 'nullable|integer|exists:genres,id',
            'audio_file' => 'required|file|mimes:mp3,wav,flac,ogg,m4a|max:51200', // Макс 50МБ
            'cover_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120', // Макс 5МБ
        ]);

        try {
            // 1. Загрузка аудио файла в Cloudflare R2
            $audioFile = $request->file('audio_file');
            $audioFileName = Str::slug($validated['title']) . '_' . time() . '.' . $audioFile->getClientOriginalExtension();
            $audioPath = 'songs/' . $audioFileName;
            
            // Загружаем в S3 (Cloudflare R2)
            $audioUrl = null;
            $disk = config('filesystems.default');
            
            if ($disk === 's3') {
                // Загружаем в Cloudflare R2 через S3 API
                Storage::disk('s3')->putFileAs('songs', $audioFile, $audioFileName, 'public');
                
                // Получаем публичный URL из Cloudflare R2
                // Cloudflare R2 использует публичный URL вида: https://pub-xxx.r2.dev/path
                $r2Url = config('filesystems.disks.s3.url');
                if ($r2Url) {
                    $audioUrl = rtrim($r2Url, '/') . '/' . $audioPath;
                } else {
                    // Если URL не настроен, используем временную ссылку
                    $audioUrl = Storage::disk('s3')->url($audioPath);
                }
            } else {
                // Если локально, сохраняем в public storage
                $audioPath = $audioFile->storeAs('songs', $audioFileName, 'public');
                $audioUrl = url('storage/' . $audioPath);
            }

            // 2. Загрузка обложки (если есть)
            $coverUrl = null;
            if ($request->hasFile('cover_file')) {
                $coverFile = $request->file('cover_file');
                $coverFileName = Str::slug($validated['title']) . '_cover_' . time() . '.' . $coverFile->getClientOriginalExtension();
                $coverPath = 'covers/' . $coverFileName;
                
                if ($disk === 's3') {
                    Storage::disk('s3')->putFileAs('covers', $coverFile, $coverFileName, 'public');
                    $r2Url = config('filesystems.disks.s3.url');
                    if ($r2Url) {
                        $coverUrl = rtrim($r2Url, '/') . '/' . $coverPath;
                    } else {
                        $coverUrl = Storage::disk('s3')->url($coverPath);
                    }
                } else {
                    $coverPath = $coverFile->storeAs('covers', $coverFileName, 'public');
                    $coverUrl = url('storage/' . $coverPath);
                }
            }

            // 3. Создание записи в БД
            $song = Song::create([
                'title' => $validated['title'],
                'artist_id' => $validated['artist_id'],
                'genre_id' => $validated['genre_id'] ?? null,
                // В нашей SQLite-схеме album NOT NULL
                'album' => $validated['album'] ?? '',
                'duration' => $validated['duration'] ?? '0:00',
                'audio_url' => $audioUrl,
                'cover_url' => $coverUrl ?? 'https://via.placeholder.com/300',
            ]);

            // Добавляем жанр, если указан
            if (isset($validated['genre_id'])) {
                $song->genres()->syncWithoutDetaching([$validated['genre_id']]);

                // Для обратной совместимости сохраняем строковый жанр в songs.genre
                $genre = Genre::find($validated['genre_id']);
                if ($genre) {
                    try {
                        $song->update(['genre' => $genre->name]);
                    } catch (\Throwable $e) {
                        // no-op
                    }
                }
            }

            // Загружаем отношение artist
            $song->load('artist');

            Log::info('Песня загружена админом', [
                'song_id' => $song->id,
                'title' => $song->title,
                'admin_id' => $request->user()->id
            ]);

            // Форматируем песню в том же формате, что и SongController
            $audioUrlFormatted = $song->audio_url;
            if ($audioUrlFormatted && !str_starts_with($audioUrlFormatted, 'http')) {
                $audioUrlFormatted = url('api/stream/' . $song->id);
            }

            $coverUrlFormatted = $song->cover_url;
            if ($coverUrlFormatted && !str_starts_with($coverUrlFormatted, 'http')) {
                $coverUrlFormatted = url('storage/' . $coverUrlFormatted);
            }

            $formattedSong = [
                'id' => $song->id,
                'title' => $song->title,
                'artist' => $song->artist ? $song->artist->name : 'Unknown Artist',
                'artist_id' => $song->artist_id,
                'album' => $song->album ?? '',
                'duration' => $song->duration ?? '0:00',
                'cover' => $coverUrlFormatted ?? 'https://via.placeholder.com/300',
                'audioUrl' => $audioUrlFormatted ?? '',
                'lyrics' => $song->lyrics ?? [],
                'color' => $song->color ?? null,
            ];

            return response()->json([
                'status' => 'success',
                'message' => 'Песня успешно загружена',
                'data' => $formattedSong
            ], 201);

        } catch (\Exception $e) {
            Log::error('Ошибка загрузки песни', [
                'error' => $e->getMessage(),
                'admin_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Ошибка при загрузке: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получение списка артистов для выбора при загрузке
     * GET /api/admin/artists
     */
    public function getArtists()
    {
        $artists = Artist::select('id', 'name')->orderBy('name')->get();
        return response()->json(['data' => $artists]);
    }
}

