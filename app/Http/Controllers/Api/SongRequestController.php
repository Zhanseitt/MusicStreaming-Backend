<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SongRequest;
use App\Models\Genre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SongRequestController extends Controller
{
    /**
     * Создать заявку на модерацию трека
     * POST /api/song-requests
     */
    public function store(Request $request)
    {
        $user = $request->user();
        
        // Проверяем, что пользователь является артистом
        if ($user->role !== 'artist') {
            return response()->json(['error' => 'Доступ запрещен. Только для артистов.'], 403);
        }

        // Валидация
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            // Новый поток: артист выбирает только жанр из БД (одобренный админом)
            'genre_id' => 'required|integer|exists:genres,id',
            // Оставляем для обратной совместимости (если фронт ещё шлёт строку)
            'genre' => 'nullable|string|max:255',
            'authors' => 'required|string',
            'lyrics' => 'nullable|string',
            'description' => 'nullable|string',
            'social_links' => 'nullable|string',
            'audio' => 'required|file|mimes:wav,mp3,flac,ogg,m4a|max:51200', // Макс 50МБ
            'cover' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120', // Макс 5МБ
            'copyright' => 'required|file|mimes:pdf,doc,docx,jpeg,jpg,png|max:10240', // Макс 10МБ
        ]);

        try {
            $disk = config('filesystems.default');

            // Нормализуем жанр: берём имя из таблицы genres по genre_id
            $genre = Genre::find($validated['genre_id']);
            if (!$genre) {
                return response()->json(['success' => false, 'error' => 'Выбранный жанр не найден'], 422);
            }
            
            // 1. Загрузка аудио файла в R2 (requests/audio)
            $audioFile = $request->file('audio');
            $audioFileName = Str::slug($validated['title']) . '_' . time() . '.' . $audioFile->getClientOriginalExtension();
            $audioPathRelative = 'requests/audio/' . $audioFileName;
            
            if ($disk === 's3') {
                Storage::disk('s3')->putFileAs('requests/audio', $audioFile, $audioFileName, 'public');
            } else {
                $audioPathRelative = $audioFile->storeAs('requests/audio', $audioFileName, 'public');
            }
            
            // Сохраняем относительный путь в БД
            $audioPath = $audioPathRelative;

            // 2. Загрузка обложки (если есть) в R2 (requests/covers)
            $coverPath = null;
            if ($request->hasFile('cover')) {
                $coverFile = $request->file('cover');
                $coverFileName = Str::slug($validated['title']) . '_cover_' . time() . '.' . $coverFile->getClientOriginalExtension();
                $coverPathRelative = 'requests/covers/' . $coverFileName;
                
                if ($disk === 's3') {
                    Storage::disk('s3')->putFileAs('requests/covers', $coverFile, $coverFileName, 'public');
                } else {
                    $coverPathRelative = $coverFile->storeAs('requests/covers', $coverFileName, 'public');
                }
                
                // Сохраняем относительный путь в БД
                $coverPath = $coverPathRelative;
            }

            // 3. Загрузка документов об авторских правах в R2 (requests/docs)
            $docFile = $request->file('copyright');
            $docFileName = Str::slug($validated['title']) . '_doc_' . time() . '.' . $docFile->getClientOriginalExtension();
            $docPathRelative = 'requests/docs/' . $docFileName;
            
            if ($disk === 's3') {
                Storage::disk('s3')->putFileAs('requests/docs', $docFile, $docFileName, 'public');
            } else {
                $docPathRelative = $docFile->storeAs('requests/docs', $docFileName, 'public');
            }
            
            // Сохраняем относительный путь в БД
            $docPath = $docPathRelative;

            // 4. Создание заявки
            $songRequest = SongRequest::create([
                'user_id' => $user->id,
                'title' => $validated['title'],
                'genre' => $genre->name,
                'genre_id' => $genre->id,
                'authors' => $validated['authors'],
                'lyrics' => $validated['lyrics'] ?? null,
                'description' => $validated['description'] ?? null,
                'social_links' => $validated['social_links'] ?? null,
                'audio_path' => $audioPath,
                'cover_path' => $coverPath,
                'doc_path' => $docPath,
                'status' => 'pending',
            ]);

            Log::info('Заявка на модерацию создана', [
                'request_id' => $songRequest->id,
                'user_id' => $user->id,
                'title' => $songRequest->title
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Заявка отправлена и находится на рассмотрении',
                'data' => $songRequest
            ], 201);

        } catch (\Exception $e) {
            Log::error('Ошибка создания заявки на модерацию', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Ошибка при отправке заявки: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить заявки текущего артиста
     * GET /api/song-requests
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        if ($user->role !== 'artist') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $requests = SongRequest::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $requests]);
    }
}
