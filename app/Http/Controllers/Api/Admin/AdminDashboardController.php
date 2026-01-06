<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Song;
use App\Models\Track;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminDashboardController extends Controller
{
    /**
     * Получить статистику для дашборда админа
     * GET /api/admin/dashboard/stats
     */
    public function getStats(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        // Зарегистрированные пользователи (без админов)
        // (UI в админке показывает именно зарегистрированных, без процентов)
        $activeUsers = User::where('role', '!=', 'admin')->count();

        // Всего треков и песен
        $totalTracks = Track::count();
        $totalSongs = Song::count();
        $totalAudioFiles = $totalTracks + $totalSongs;

        // Расчет использования хранилища
        $storageInfo = $this->calculateStorageUsage();

        // Последние действия для аудита
        $auditLogs = $this->getAuditLogs();

        return response()->json([
            'status' => 'success',
            'data' => [
                'active_users' => $activeUsers,
                'total_songs' => $totalSongs,
                'total_tracks' => $totalTracks,
                'total_audio_files' => $totalAudioFiles,
                'storage' => $storageInfo,
                'audit_logs' => $auditLogs
            ]
        ]);
    }

    /**
     * Получить аудит логи
     */
    private function getAuditLogs()
    {
        $logs = [];

        // Последние загруженные песни
        $recentSongs = Song::with('artist')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($song) {
                return [
                    'id' => $song->id,
                    'time' => $song->created_at->format('H:i'),
                    'action' => 'Загружена новая песня: ' . $song->title,
                    'user' => $song->artist->name ?? 'Unknown',
                    'type' => 'success'
                ];
            });

        // Последние комментарии
        $recentComments = Comment::with('user', 'song')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($comment) {
                $typeMap = [
                    'positive' => 'success',
                    'negative' => 'error',
                    'suggestion' => 'info',
                    'neutral' => 'info'
                ];
                return [
                    'id' => $comment->id,
                    'time' => $comment->created_at->format('H:i'),
                    'action' => 'Новый комментарий к: ' . ($comment->song->title ?? 'Unknown'),
                    'user' => $comment->user->name ?? 'Unknown',
                    'type' => $typeMap[$comment->type ?? 'neutral']
                ];
            });

        // Объединяем и сортируем по времени
        $allLogs = collect()
            ->merge($recentSongs)
            ->merge($recentComments)
            ->sortByDesc('time')
            ->take(10)
            ->values();

        return $allLogs;
    }

    /**
     * Расчет использования хранилища
     * 
     * @return array
     */
    private function calculateStorageUsage()
    {
        $disk = config('filesystems.default');
        $usedBytes = 0;
        $fileCount = 0;
        $maxStorageBytes = 1024 * 1024 * 1024 * 1024; // 1 TB по умолчанию

        try {
            if ($disk === 's3') {
                // Для R2/S3 считаем размер файлов из базы данных
                // Получаем все треки и песни с их путями
                $tracks = Track::whereNotNull('file_path')->get();
                $songs = Song::whereNotNull('audio_url')->get();
                
                foreach ($tracks as $track) {
                    $path = $track->file_path ?? $track->audio_url;
                    if ($path && !str_starts_with($path, 'http')) {
                        try {
                            if (Storage::disk('s3')->exists($path)) {
                                $size = Storage::disk('s3')->size($path);
                                $usedBytes += $size;
                                $fileCount++;
                            }
                        } catch (\Exception $e) {
                            Log::warning('Не удалось получить размер файла: ' . $path);
                        }
                    }
                }

                foreach ($songs as $song) {
                    $path = $song->audio_url;
                    if ($path && !str_starts_with($path, 'http')) {
                        try {
                            if (Storage::disk('s3')->exists($path)) {
                                $size = Storage::disk('s3')->size($path);
                                $usedBytes += $size;
                                $fileCount++;
                            }
                        } catch (\Exception $e) {
                            Log::warning('Не удалось получить размер файла: ' . $path);
                        }
                    }
                }

                // Также считаем обложки
                foreach ($tracks as $track) {
                    if ($track->cover_url && !str_starts_with($track->cover_url, 'http')) {
                        try {
                            if (Storage::disk('s3')->exists($track->cover_url)) {
                                $size = Storage::disk('s3')->size($track->cover_url);
                                $usedBytes += $size;
                                $fileCount++;
                            }
                        } catch (\Exception $e) {
                            // Игнорируем ошибки
                        }
                    }
                }

                foreach ($songs as $song) {
                    if ($song->cover_url && !str_starts_with($song->cover_url, 'http')) {
                        try {
                            if (Storage::disk('s3')->exists($song->cover_url)) {
                                $size = Storage::disk('s3')->size($song->cover_url);
                                $usedBytes += $size;
                                $fileCount++;
                            }
                        } catch (\Exception $e) {
                            // Игнорируем ошибки
                        }
                    }
                }
            } else {
                // Локальное хранилище
                $audioPath = storage_path('app/public/audio');
                $coversPath = storage_path('app/public/covers');
                
                if (is_dir($audioPath)) {
                    $iterator = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($audioPath)
                    );
                    foreach ($iterator as $file) {
                        if ($file->isFile()) {
                            $usedBytes += $file->getSize();
                            $fileCount++;
                        }
                    }
                }
                
                if (is_dir($coversPath)) {
                    $iterator = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($coversPath)
                    );
                    foreach ($iterator as $file) {
                        if ($file->isFile()) {
                            $usedBytes += $file->getSize();
                            $fileCount++;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Ошибка расчета хранилища: ' . $e->getMessage());
        }

        $usedGB = $usedBytes / (1024 * 1024 * 1024);
        $maxStorageGB = $maxStorageBytes / (1024 * 1024 * 1024);
        $usagePercent = $maxStorageGB > 0 ? ($usedGB / $maxStorageGB) * 100 : 0;

        return [
            'used_bytes' => $usedBytes,
            'used_gb' => round($usedGB, 2),
            'max_gb' => $maxStorageGB,
            'usage_percent' => round($usagePercent, 2),
            'file_count' => $fileCount,
            'storage_type' => $disk === 's3' ? 'R2' : 'Local'
        ];
    }
}
