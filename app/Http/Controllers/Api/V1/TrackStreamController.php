<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Track;
use Illuminate\Support\Facades\Storage;

class TrackStreamController extends Controller
{
    public function stream(Track $track)
    {
        // Здесь можно проверить подписку пользователя
        // if (!auth()->user()->hasActiveSubscription()) abort(403);

        $path = $track->file_path;
        $disk = config('filesystems.default'); // 'public' или 's3'

        if ($disk === 's3') {
            // Если мы на AWS, генерируем временную ссылку на 1 час
            $url = Storage::disk('s3')->temporaryUrl(
                $path, now()->addHour()
            );
            return response()->json(['url' => $url]);
        } else {
            // Если мы локально, отдаем локальный URL
            // В продакшене лучше использовать X-Sendfile или nginx streaming
            $url = asset('storage/' . $path);
            return response()->json(['url' => $url]);
        }
    }
}