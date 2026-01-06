<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Song;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SongEditController extends Controller
{
    /**
     * Обновить песню
     * PUT /api/admin/songs/{song}
     */
    public function update(Request $request, Song $song)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'artist_id' => 'sometimes|required|exists:artists,id',
            'album' => 'nullable|string|max:255',
            'duration' => 'nullable|string',
            'audio_file' => 'sometimes|file|mimes:mp3,wav,flac,ogg|max:51200',
            'cover_file' => 'sometimes|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        try {
            // Обновляем аудио файл, если загружен новый
            if ($request->hasFile('audio_file')) {
                $audioFile = $request->file('audio_file');
                $audioFileName = Str::slug($validated['title'] ?? $song->title) . '_' . time() . '.' . $audioFile->getClientOriginalExtension();
                $audioPath = 'songs/' . $audioFileName;
                
                $disk = config('filesystems.default');
                
                if ($disk === 's3') {
                    Storage::disk('s3')->put($audioPath, file_get_contents($audioFile->getRealPath()), 'public');
                    $r2Url = config('filesystems.disks.s3.url');
                    $audioUrl = $r2Url ? rtrim($r2Url, '/') . '/' . $audioPath : Storage::disk('s3')->url($audioPath);
                } else {
                    $audioPath = $audioFile->storeAs('songs', $audioFileName, 'public');
                    $audioUrl = url('storage/' . $audioPath);
                }
                
                $validated['audio_url'] = $audioUrl;
            }

            // Обновляем обложку, если загружена новая
            if ($request->hasFile('cover_file')) {
                $coverFile = $request->file('cover_file');
                $coverFileName = Str::slug($validated['title'] ?? $song->title) . '_cover_' . time() . '.' . $coverFile->getClientOriginalExtension();
                $coverPath = 'covers/' . $coverFileName;
                
                $disk = config('filesystems.default');
                
                if ($disk === 's3') {
                    Storage::disk('s3')->put($coverPath, file_get_contents($coverFile->getRealPath()), 'public');
                    $r2Url = config('filesystems.disks.s3.url');
                    $coverUrl = $r2Url ? rtrim($r2Url, '/') . '/' . $coverPath : Storage::disk('s3')->url($coverPath);
                } else {
                    $coverPath = $coverFile->storeAs('covers', $coverFileName, 'public');
                    $coverUrl = url('storage/' . $coverPath);
                }
                
                $validated['cover_url'] = $coverUrl;
            }

            // Обновляем песню
            $song->update($validated);
            $song->load('artist');

            Log::info('Песня обновлена админом', [
                'song_id' => $song->id,
                'admin_id' => $request->user()->id
            ]);

            // Форматируем ответ
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
                'success' => true,
                'message' => 'Песня успешно обновлена',
                'song' => $formattedSong
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка обновления песни', [
                'error' => $e->getMessage(),
                'song_id' => $song->id,
                'admin_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Ошибка при обновлении: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Извлечь путь к файлу из URL для удаления из хранилища
     */
    private function extractFilePathFromUrl($url, $disk = 's3')
    {
        if (empty($url)) {
            return null;
        }

        // Если URL начинается с http, извлекаем путь
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $parsedUrl = parse_url($url);
            $path = $parsedUrl['path'] ?? '';
            
            // Удаляем начальные слэши
            $path = ltrim($path, '/');
            
            // Если это локальный storage путь (например, storage/songs/filename.mp3)
            if (str_starts_with($path, 'storage/')) {
                return substr($path, 8); // Убираем 'storage/'
            }
            
            // Если это путь к файлу на R2/S3 (например, songs/filename.mp3 или covers/filename.jpg)
            if (str_starts_with($path, 'songs/') || str_starts_with($path, 'covers/')) {
                return $path;
            }
            
            // Если URL содержит домен R2/S3, извлекаем путь после домена
            if ($disk === 's3' || $disk === 'r2') {
                $r2Url = config('filesystems.disks.s3.url');
                if ($r2Url) {
                    $r2UrlParsed = parse_url($r2Url);
                    $r2BaseUrl = ($r2UrlParsed['scheme'] ?? 'https') . '://' . ($r2UrlParsed['host'] ?? '');
                    
                    if (str_starts_with($url, $r2BaseUrl)) {
                        // Извлекаем путь после домена
                        $relativePath = str_replace($r2BaseUrl, '', $url);
                        $relativePath = ltrim($relativePath, '/');
                        // Убираем query string, если есть
                        if (strpos($relativePath, '?') !== false) {
                            $relativePath = substr($relativePath, 0, strpos($relativePath, '?'));
                        }
                        return $relativePath;
                    }
                }
            }
            
            // Если ничего не подошло, пытаемся извлечь путь после последнего слэша домена
            // (для случаев, когда путь может быть в другом формате)
            return null;
        }
        
        // Если это уже путь (без http), возвращаем как есть
        return $url;
    }

    /**
     * Удалить песню
     * DELETE /api/admin/songs/{song}
     */
    public function destroy(Request $request, Song $song)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        try {
            $songId = $song->id;
            $audioUrl = $song->audio_url;
            $coverUrl = $song->cover_url;
            $disk = config('filesystems.default');
            
            // Удаляем файлы из хранилища перед удалением записи
            if ($disk === 's3' || $disk === 'r2') {
                // Удаляем аудио файл
                if ($audioUrl) {
                    $audioPath = $this->extractFilePathFromUrl($audioUrl, $disk);
                    if ($audioPath && Storage::disk('s3')->exists($audioPath)) {
                        Storage::disk('s3')->delete($audioPath);
                        Log::info('Аудио файл удален из R2/S3', [
                            'path' => $audioPath,
                            'song_id' => $songId
                        ]);
                    }
                }
                
                // Удаляем обложку
                if ($coverUrl) {
                    $coverPath = $this->extractFilePathFromUrl($coverUrl, $disk);
                    if ($coverPath && Storage::disk('s3')->exists($coverPath)) {
                        Storage::disk('s3')->delete($coverPath);
                        Log::info('Обложка удалена из R2/S3', [
                            'path' => $coverPath,
                            'song_id' => $songId
                        ]);
                    }
                }
            } elseif ($disk === 'public' || $disk === 'local') {
                // Удаляем из локального хранилища
                if ($audioUrl) {
                    $audioPath = $this->extractFilePathFromUrl($audioUrl, $disk);
                    if ($audioPath && Storage::disk('public')->exists($audioPath)) {
                        Storage::disk('public')->delete($audioPath);
                        Log::info('Аудио файл удален из локального хранилища', [
                            'path' => $audioPath,
                            'song_id' => $songId
                        ]);
                    }
                }
                
                if ($coverUrl) {
                    $coverPath = $this->extractFilePathFromUrl($coverUrl, $disk);
                    if ($coverPath && Storage::disk('public')->exists($coverPath)) {
                        Storage::disk('public')->delete($coverPath);
                        Log::info('Обложка удалена из локального хранилища', [
                            'path' => $coverPath,
                            'song_id' => $songId
                        ]);
                    }
                }
            }
            
            // Удаляем запись из базы данных
            $song->delete();

            Log::info('Песня удалена админом', [
                'song_id' => $songId,
                'admin_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Песня успешно удалена'
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка удаления песни', [
                'error' => $e->getMessage(),
                'song_id' => $song->id ?? null,
                'admin_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Ошибка при удалении: ' . $e->getMessage()
            ], 500);
        }
    }
}
