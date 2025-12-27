<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Track;
use App\Models\Artist;
use App\Models\Album;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TrackUploadController extends Controller
{
    public function store(Request $request)
    {
        // 1. Валидация
        $request->validate([
            'title' => 'required|string',
            'artist_id' => 'required|exists:artists,id',
            'album_id' => 'nullable|exists:albums,id',
            'track_file' => 'required|file|mimes:mp3,wav,flac|max:20480', // Макс 20МБ
            'cover_file' => 'nullable|image|max:5120',
        ]);

        // 2. Загрузка файла (Работает и локально, и на S3)
        // Файл попадет в storage/app/public/tracks
        $path = $request->file('track_file')->store('tracks', 'public');

        // 3. Создание записи в БД
        $track = Track::create([
            'title' => $request->title,
            'artist_id' => $request->artist_id,
            'album_id' => $request->album_id,
            'file_path' => $path, // Сохраняем только путь, а не полный URL
            'duration' => 240, // Пока хардкод, позже можно достать метаданные из mp3
        ]);

        return response()->json([
            'message' => 'Трек успешно загружен',
            'track' => $track
        ], 201);
    }
}