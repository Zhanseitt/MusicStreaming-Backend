<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Track;
use App\Models\Artist;
use App\Models\Album;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TrackUploadController extends Controller
{
    /**
     * Загрузка нового трека админом
     * POST /api/admin/tracks/upload
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Проверяем, что пользователь - админ
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'status' => 'error',
                'error' => 'Доступ запрещен'
            ], 403);
        }

        // Валидация
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'artist_id' => 'required|exists:artists,id',
            'album_id' => 'nullable|exists:albums,id',
            'duration' => 'nullable|string',
            'audio_file' => 'required|file|mimes:mp3,wav,flac,ogg,m4a|max:51200', // Макс 50МБ
            'cover_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120', // Макс 5МБ
            'bpm' => 'nullable|integer|min:1|max:300',
            'lyrics' => 'nullable|string',
        ]);

        try {
            $disk = config('filesystems.default');
            
            // 1. Загрузка аудио файла в Cloudflare R2
            $audioFile = $request->file('audio_file');
            $audioFileName = Str::slug($validated['title']) . '_' . time() . '.' . $audioFile->getClientOriginalExtension();
            $audioPath = 'audio/' . $audioFileName;
            
            $audioUrl = null;
            
            if ($disk === 's3') {
                // Загружаем в Cloudflare R2 через S3 API
                Storage::disk('s3')->putFileAs('audio', $audioFile, $audioFileName, 'public');
                
                // Получаем публичный URL из Cloudflare R2
                $r2Url = config('filesystems.disks.s3.url');
                if ($r2Url) {
                    $audioUrl = rtrim($r2Url, '/') . '/' . $audioPath;
                } else {
                    // Если URL не настроен, используем временную ссылку
                    $audioUrl = Storage::disk('s3')->url($audioPath);
                }
            } else {
                // Если локально, сохраняем в public storage
                $audioPath = $audioFile->storeAs('audio', $audioFileName, 'public');
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

            // 3. Парсинг длительности (если строка вида "3:20", конвертируем в секунды)
            $duration = $validated['duration'] ?? '0:00';
            $durationSeconds = $this->parseDuration($duration);

            // 4. Создание записи в БД
            $track = Track::create([
                'title' => $validated['title'],
                'artist_id' => $validated['artist_id'],
                'album_id' => $validated['album_id'] ?? null,
                'duration' => $durationSeconds,
                'file_path' => $audioPath,
                'audio_url' => $audioUrl,
                'cover_url' => $coverUrl,
                'bpm' => $validated['bpm'] ?? null,
                'lyrics' => $validated['lyrics'] ? json_decode($validated['lyrics'], true) : null,
                'listens_count' => 0,
            ]);

            // Загружаем отношения
            $track->load(['artist', 'album']);

            Log::info('Трек загружен админом', [
                'track_id' => $track->id,
                'title' => $track->title,
                'admin_id' => $request->user()->id
            ]);

            // Форматируем трек для ответа
            $formattedTrack = $this->formatTrackForResponse($track);

            return response()->json([
                'status' => 'success',
                'message' => 'Трек успешно загружен',
                'data' => $formattedTrack
            ], 201);

        } catch (\Exception $e) {
            Log::error('Ошибка загрузки трека', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'admin_id' => $request->user()->id
            ]);

            return response()->json([
                'status' => 'error',
                'error' => 'Ошибка при загрузке: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получение списка артистов для выбора при загрузке
     * GET /api/admin/tracks/artists
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getArtists()
    {
        $artists = Artist::select('id', 'name')->orderBy('name')->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $artists
        ]);
    }

    /**
     * Парсинг длительности из строки MM:SS в секунды
     * 
     * @param string|int $duration
     * @return int
     */
    private function parseDuration($duration)
    {
        if (is_int($duration)) {
            return $duration;
        }

        if (preg_match('/^(\d+):(\d{2})$/', $duration, $matches)) {
            $minutes = (int) $matches[1];
            $seconds = (int) $matches[2];
            return $minutes * 60 + $seconds;
        }

        return (int) $duration;
    }

    /**
     * Форматирование трека для ответа API
     * 
     * @param Track $track
     * @return array
     */
    private function formatTrackForResponse($track)
    {
        $duration = $track->duration ?? 0;
        $durationFormatted = $this->formatDuration($duration);

        $audioUrl = $track->audio_url ?? $track->file_path ?? '';
        if ($audioUrl && !str_starts_with($audioUrl, 'http')) {
            $audioUrl = url('api/tracks/' . $track->id . '/stream');
        }

        $coverUrl = $track->cover_url ?? '';
        if ($coverUrl && !str_starts_with($coverUrl, 'http')) {
            $disk = config('filesystems.default');
            if ($disk === 's3') {
                $r2Url = config('filesystems.disks.s3.url');
                if ($r2Url) {
                    $coverUrl = rtrim($r2Url, '/') . '/' . $coverUrl;
                }
            } else {
                $coverUrl = url('storage/' . $coverUrl);
            }
        }

        return [
            'id' => $track->id,
            'title' => $track->title,
            'artist' => $track->artist ? $track->artist->name : 'Unknown Artist',
            'artist_id' => $track->artist_id,
            'album' => $track->album ? $track->album->title : null,
            'album_id' => $track->album_id,
            'duration' => $durationFormatted,
            'cover' => $coverUrl ?: 'https://via.placeholder.com/300',
            'audioUrl' => $audioUrl ?: '',
            'lyrics' => $track->lyrics ?? [],
            'listens_count' => $track->listens_count ?? 0,
        ];
    }

    /**
     * Форматирование длительности из секунд в MM:SS
     * 
     * @param int $seconds
     * @return string
     */
    private function formatDuration($seconds)
    {
        if (is_string($seconds)) {
            if (preg_match('/^\d+:\d{2}$/', $seconds)) {
                return $seconds;
            }
            $seconds = (int) $seconds;
        }

        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;
        return sprintf('%d:%02d', $minutes, $secs);
    }
}

