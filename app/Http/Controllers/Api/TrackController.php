<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Track;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TrackController extends Controller
{
    /**
     * Список всех треков
     * GET /api/tracks
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        Log::info('GET /api/tracks');

        $tracks = Track::with(['artist', 'album'])
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $tracks->map(function($track) {
                return $this->formatTrack($track);
            })
        ]);
    }

    /**
     * Детальная информация о треке
     * GET /api/tracks/{track}
     * 
     * @param Track $track
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Track $track)
    {
        $track->load(['artist', 'album']);
        
        return response()->json([
            'status' => 'success',
            'data' => $this->formatTrack($track)
        ]);
    }

    /**
     * Поиск треков
     * GET /api/tracks/search?q=query
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        $query = $request->input('q');

        if (!$query) {
            return response()->json([
                'status' => 'success',
                'data' => []
            ]);
        }

        $tracks = Track::with(['artist', 'album'])
            ->where('title', 'LIKE', "%{$query}%")
            ->orWhereHas('artist', function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%");
            })
            ->orWhereHas('album', function($q) use ($query) {
                $q->where('title', 'LIKE', "%{$query}%");
            })
            ->limit(20)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $tracks->map(function($track) {
                return $this->formatTrack($track);
            })
        ]);
    }

    /**
     * Трендовые треки
     * GET /api/tracks/trending
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function trending()
    {
        $tracks = Track::with(['artist', 'album'])
            ->orderBy('listens_count', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $tracks->map(function($track) {
                return $this->formatTrack($track);
            })
        ]);
    }

    /**
     * Потоковая передача аудио из R2
     * GET /api/tracks/{id}/stream
     * 
     * @param int $id
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function stream($id)
    {
        $track = Track::findOrFail($id);
        
        // Увеличиваем счётчик прослушиваний
        $track->incrementListens();

        $audioPath = $track->audio_url ?? $track->file_path;
        
        // Если URL начинается с http, это внешняя ссылка (R2)
        if (str_starts_with($audioPath, 'http')) {
            return redirect($audioPath);
        }
        
        // Если путь в R2 хранилище
        $disk = config('filesystems.default');
        if ($disk === 's3' && Storage::disk('s3')->exists($audioPath)) {
            // Возвращаем временную ссылку для стриминга
            $url = Storage::disk('s3')->temporaryUrl($audioPath, now()->addHours(1));
            return redirect($url);
        }
        
        // Локальное хранилище (fallback)
        $path = storage_path('app/public/' . $audioPath);
        
        if (!file_exists($path)) {
            abort(404, 'Audio file not found: ' . $audioPath);
        }

        return response()->file($path, [
            'Content-Type' => 'audio/mpeg',
            'Accept-Ranges' => 'bytes',
        ]);
    }

    /**
     * Форматирование трека для API
     * 
     * @param Track $track
     * @return array
     */
    private function formatTrack($track)
    {
        // Получаем URL аудио
        $audioUrl = $track->audio_url ?? $track->file_path ?? '';
        
        // Если это путь в R2, получаем публичный URL
        if ($audioUrl && !str_starts_with($audioUrl, 'http')) {
            $disk = config('filesystems.default');
            if ($disk === 's3') {
                // Проверяем, существует ли файл в R2
                if (Storage::disk('s3')->exists($audioUrl)) {
                    // Используем публичный URL из конфига или временную ссылку
                    $r2Url = config('filesystems.disks.s3.url');
                    if ($r2Url) {
                        $audioUrl = rtrim($r2Url, '/') . '/' . $audioUrl;
                    } else {
                        $audioUrl = Storage::disk('s3')->url($audioUrl);
                    }
                }
            } else {
                // Локальное хранилище
                $audioUrl = url('api/tracks/' . $track->id . '/stream');
            }
        }

        // Получаем URL обложки
        $coverUrl = $track->cover_url ?? '';
        if ($coverUrl && !str_starts_with($coverUrl, 'http')) {
            $disk = config('filesystems.default');
            if ($disk === 's3') {
                $r2Url = config('filesystems.disks.s3.url');
                if ($r2Url && Storage::disk('s3')->exists($coverUrl)) {
                    $coverUrl = rtrim($r2Url, '/') . '/' . $coverUrl;
                } else {
                    $coverUrl = Storage::disk('s3')->exists($coverUrl) 
                        ? Storage::disk('s3')->url($coverUrl) 
                        : $coverUrl;
                }
            } else {
                $coverUrl = url('storage/' . $coverUrl);
            }
        }

        // Форматируем длительность
        $duration = $track->duration ?? 0;
        $durationFormatted = $this->formatDuration($duration);

        return [
            'id' => $track->id,
            'title' => $track->title,
            'artist' => $track->artist ? $track->artist->name : 'Unknown Artist',
            'artist_id' => $track->artist_id,
            'album' => $track->album ? $track->album->title : null,
            'album_id' => $track->album_id,
            'duration' => $durationFormatted,
            'cover' => $coverUrl ?: 'https://via.placeholder.com/300',
            'audioUrl' => $audioUrl ?: url('api/tracks/' . $track->id . '/stream'),
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
            // Если уже строка формата MM:SS, возвращаем как есть
            if (preg_match('/^\d+:\d{2}$/', $seconds)) {
                return $seconds;
            }
            // Пытаемся распарсить
            $seconds = (int) $seconds;
        }

        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;
        return sprintf('%d:%02d', $minutes, $secs);
    }
}

