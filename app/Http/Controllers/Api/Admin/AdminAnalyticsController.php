<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Song;
use App\Models\Genre;
use App\Models\ListeningHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminAnalyticsController extends Controller
{
    /**
     * Получить аналитику для админ-панели
     * GET /api/admin/analytics
     */
    public function index(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        return response()->json([
            'top_tracks' => $this->getTopTracks(),
            'daily_activity' => $this->getDailyActivity(),
            'genre_stats' => $this->getGenreStats(),
        ]);
    }

    /**
     * Топ-5 треков по play_count
     */
    private function getTopTracks()
    {
        return Song::with('artist')
            ->orderBy('play_count', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($song) {
                return [
                    'id' => $song->id,
                    'title' => $song->title,
                    'artist' => $song->artist->name ?? 'Unknown',
                    'plays' => (int) ($song->play_count ?? 0),
                    'cover' => $song->cover_url,
                ];
            });
    }

    /**
     * Прослушивания по дням за последние 7 дней
     * Возвращает массив: [{ day: "Сегодня", plays: 123 }, { day: "Вс", plays: 45 }, ...]
     */
    private function getDailyActivity()
    {
        // Названия дней недели на русском (индекс = dayOfWeek, где 0=Вс, 1=Пн...)
        $dayNames = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];

        // Группируем прослушивания по дням за последнюю неделю
        $rawData = ListeningHistory::select(
                DB::raw('DATE(played_at) as date'),
                DB::raw('COUNT(*) as plays')
            )
            ->where('played_at', '>=', Carbon::now()->subDays(6)->startOfDay())
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get()
            ->keyBy('date');

        $result = [];
        for ($i = 0; $i <= 6; $i++) {
            $date = Carbon::now()->subDays($i);
            $formattedDate = $date->format('Y-m-d');
            $plays = $rawData->has($formattedDate) ? (int) $rawData[$formattedDate]->plays : 0;

            // Первый день (i=0) = "Сегодня", остальные — название дня недели
            $dayLabel = $i === 0 ? 'Сегодня' : $dayNames[$date->dayOfWeek];

            $result[] = [
                'day' => $dayLabel,
                'plays' => $plays,
                'date' => $formattedDate,
            ];
        }

        // Разворачиваем, чтобы шёл от старого к новому (Пн, Вт, ... Сегодня)
        return array_reverse($result);
    }

    /**
     * Топ-4 жанра по прослушиваниям с процентами
     */
    private function getGenreStats()
    {
        // Считаем прослушивания по жанрам через listening_history -> songs -> genre_id
        $genrePlays = DB::table('listening_history')
            ->join('songs', 'songs.id', '=', 'listening_history.song_id')
            ->join('genres', 'genres.id', '=', 'songs.genre_id')
            ->select('genres.id', 'genres.name', DB::raw('COUNT(*) as plays'))
            ->groupBy('genres.id', 'genres.name')
            ->orderBy('plays', 'desc')
            ->limit(4)
            ->get();

        // Если жанров с прослушиваниями нет, fallback на первые 4 жанра из БД
        if ($genrePlays->isEmpty()) {
            $fallback = Genre::limit(4)->get();
            if ($fallback->isEmpty()) {
                return [];
            }
            // Возвращаем с нулевыми прослушиваниями и равными процентами
            $percent = $fallback->count() > 0 ? round(100 / $fallback->count()) : 0;
            return $fallback->map(function ($g) use ($percent) {
                return [
                    'id' => $g->id,
                    'name' => $g->name,
                    'plays' => 0,
                    'percent' => $percent,
                ];
            })->values()->toArray();
        }

        // Считаем общее число прослушиваний по этим 4 жанрам (для расчёта процентов относительно друг друга)
        $totalPlays = $genrePlays->sum('plays');

        return $genrePlays->map(function ($row) use ($totalPlays) {
            return [
                'id' => $row->id,
                'name' => $row->name,
                'plays' => (int) $row->plays,
                'percent' => $totalPlays > 0 ? round(($row->plays / $totalPlays) * 100) : 0,
            ];
        })->values()->toArray();
    }
}
