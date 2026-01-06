<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SongRequest;
use App\Models\Song;
use App\Models\Artist;
use App\Models\Genre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SongModerationController extends Controller
{
    /**
     * Получить все заявки на модерацию
     * GET /api/admin/song-requests
     */
    public function index(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $status = $request->query('status', 'pending');
        
        $requests = SongRequest::with('user')
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($request) {
                return [
                    'id' => $request->id,
                    'user_id' => $request->user_id,
                    'user_name' => $request->user->name ?? 'Unknown',
                    'title' => $request->title,
                    'genre' => $request->genre,
                    'genre_id' => $request->genre_id,
                    'authors' => $request->authors,
                    'lyrics' => $request->lyrics,
                    'description' => $request->description,
                    'social_links' => $request->social_links,
                    'audio_path' => $this->getFileUrl($request->audio_path),
                    'cover_path' => $request->cover_path ? $this->getFileUrl($request->cover_path) : null,
                    'doc_path' => $request->doc_path ? $this->getFileUrl($request->doc_path) : null,
                    'status' => $request->status,
                    'rejection_reason' => $request->rejection_reason,
                    'created_at' => $request->created_at,
                ];
            });

        return response()->json(['data' => $requests]);
    }

    /**
     * Одобрить заявку
     * POST /api/admin/song-requests/{id}/approve
     */
    public function approve(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $songRequest = SongRequest::findOrFail($id);

        if ($songRequest->status !== 'pending') {
            return response()->json(['error' => 'Заявка уже обработана'], 400);
        }

        DB::beginTransaction();
        try {
            // Находим или создаем артиста по имени пользователя
            $user = $songRequest->user;
            $artist = Artist::where('name', $user->name)->first();
            
            if (!$artist) {
                // Создаем артиста, если его нет
                $artist = Artist::create([
                    'name' => $user->name,
                    'cover_url' => $songRequest->cover_path,
                ]);
            }

            // Копируем файлы из requests в основные папки
            // Извлекаем относительный путь из полного URL, если это URL
            $audioRelativePath = $songRequest->audio_path;
            $r2BaseUrl = config('filesystems.disks.s3.url');
            if ($r2BaseUrl && str_starts_with($songRequest->audio_path, $r2BaseUrl)) {
                $audioRelativePath = str_replace(rtrim($r2BaseUrl, '/') . '/', '', $songRequest->audio_path);
            }
            
            $audioPath = $this->moveFile($audioRelativePath, 'songs');
            
            $coverPath = null;
            if ($songRequest->cover_path) {
                $coverRelativePath = $songRequest->cover_path;
                if ($r2BaseUrl && str_starts_with($songRequest->cover_path, $r2BaseUrl)) {
                    $coverRelativePath = str_replace(rtrim($r2BaseUrl, '/') . '/', '', $songRequest->cover_path);
                }
                $coverPath = $this->moveFile($coverRelativePath, 'covers');
            }

            // Создаем песню в основной таблице
            $song = Song::create([
                'title' => $songRequest->title,
                'artist_id' => $artist->id,
                // В нашей SQLite-схеме album NOT NULL, поэтому используем пустую строку
                'album' => '',
                'duration' => '0:00', // Можно добавить расчет длительности позже
                'audio_url' => $audioPath,
                'cover_url' => $coverPath ?? 'https://via.placeholder.com/300',
                'status' => 'approved',
            ]);

            // Добавляем жанр: приоритет — genre_id (новый поток), иначе — поиск по имени (старый поток)
            $genre = null;
            if (!empty($songRequest->genre_id)) {
                $genre = Genre::find($songRequest->genre_id);
            }
            if (!$genre && !empty($songRequest->genre)) {
                $genre = Genre::where('name', $songRequest->genre)->first();
            }
            if ($genre) {
                // 1) сохраняем связь
                $song->genres()->syncWithoutDetaching([$genre->id]);
                // 2) сохраняем строковый жанр для обратной совместимости (/charts/genre и др.)
                try {
                    $song->update([
                        'genre' => $genre->name,
                        'genre_id' => $genre->id,
                    ]);
                } catch (\Throwable $e) {
                    // no-op
                }
            }

            // Обновляем статус заявки
            $songRequest->update(['status' => 'approved']);

            DB::commit();

            Log::info('Заявка одобрена', [
                'request_id' => $songRequest->id,
                'song_id' => $song->id,
                'admin_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Заявка одобрена, трек опубликован',
                'song' => $song
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка одобрения заявки', [
                'error' => $e->getMessage(),
                'request_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Ошибка при одобрении заявки: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отклонить заявку
     * POST /api/admin/song-requests/{id}/reject
     */
    public function reject(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $songRequest = SongRequest::findOrFail($id);

        if ($songRequest->status !== 'pending') {
            return response()->json(['error' => 'Заявка уже обработана'], 400);
        }

        $songRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        Log::info('Заявка отклонена', [
            'request_id' => $songRequest->id,
            'admin_id' => $request->user()->id,
            'reason' => $validated['rejection_reason']
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Заявка отклонена'
        ]);
    }

    /**
     * Переместить файл из requests в основную папку
     */
    private function moveFile($oldPath, $newFolder)
    {
        $disk = config('filesystems.default');
        
        // Если путь уже является полным URL, извлекаем относительный путь
        $relativeOldPath = $oldPath;
        $r2Url = config('filesystems.disks.s3.url');
        if ($r2Url && str_starts_with($oldPath, $r2Url)) {
            $relativeOldPath = str_replace(rtrim($r2Url, '/') . '/', '', $oldPath);
        }
        $relativeOldPath = ltrim($relativeOldPath, '/');
        
        $fileName = basename($relativeOldPath);
        $newPath = ltrim($newFolder . '/' . $fileName, '/');

        if ($disk === 's3') {
            if (!Storage::disk('s3')->exists($relativeOldPath)) {
                throw new \RuntimeException("Источник не найден в хранилище: {$relativeOldPath}");
            }

            // Cloudflare R2 иногда не поддерживает server-side copy (CopyObject) в текущих настройках.
            // Поэтому делаем: сначала пробуем copy(), если не получилось — копируем через stream.
            $copied = false;
            try {
                $copied = (bool) Storage::disk('s3')->copy($relativeOldPath, $newPath);
            } catch (\Throwable $e) {
                $copied = false;
            }

            if (!$copied) {
                $read = Storage::disk('s3')->readStream($relativeOldPath);
                if (!$read) {
                    throw new \RuntimeException("Не удалось открыть поток для чтения: {$relativeOldPath}");
                }

                $writeOk = Storage::disk('s3')->writeStream($newPath, $read, ['visibility' => 'public']);
                if (is_resource($read)) {
                    fclose($read);
                }
                if (!$writeOk) {
                    throw new \RuntimeException("Unable to copy file from {$relativeOldPath} to {$newPath}");
                }
            }

            return $r2Url ? rtrim($r2Url, '/') . '/' . $newPath : Storage::disk('s3')->url($newPath);
        } else {
            // Локально
            if (Storage::disk('public')->exists($relativeOldPath)) {
                $ok = Storage::disk('public')->copy($relativeOldPath, $newPath);
                if (!$ok) {
                    throw new \RuntimeException("Unable to copy file from {$relativeOldPath} to {$newPath}");
                }
            } else {
                throw new \RuntimeException("Источник не найден в хранилище: {$relativeOldPath}");
            }
            return url('storage/' . $newPath);
        }
    }

    /**
     * Получить URL файла
     */
    private function getFileUrl($path)
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        $disk = config('filesystems.default');
        if ($disk === 's3') {
            $r2Url = config('filesystems.disks.s3.url');
            return $r2Url ? rtrim($r2Url, '/') . '/' . $path : Storage::disk('s3')->url($path);
        } else {
            return url('storage/' . $path);
        }
    }
}
