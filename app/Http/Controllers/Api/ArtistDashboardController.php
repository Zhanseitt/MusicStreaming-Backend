<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\ListeningHistory;
use App\Models\Song;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArtistDashboardController extends Controller
{
    /**
     * Получить статистику артиста
     * GET /api/artist/dashboard/stats
     */
    public function getStats(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'artist') {
            return response()->json(['error' => 'Доступ запрещен. Только для артистов.'], 403);
        }

        // В текущей схеме связь user->artist отсутствует, поэтому привязываемся по имени.
        $artist = Artist::where('name', $user->name)->first();
        if (!$artist) {
            return response()->json([
                'total_plays' => 0,
                'monthly_listeners' => 0,
                'total_songs' => 0,
                'revenue' => '$0',
                'total_likes' => 0,
                'followers' => 0,
            ]);
        }

        // Базовый query по истории прослушиваний артиста без pluck/id массива
        $historyQuery = ListeningHistory::query()
            ->whereHas('song', function ($q) use ($artist) {
                $q->where('artist_id', $artist->id);
            });

        $totalPlays = (clone $historyQuery)->count();

        $monthlyListeners = (clone $historyQuery)
            ->where('played_at', '>=', Carbon::now()->subDays(30))
            ->distinct('user_id')
            ->count('user_id');

        $totalSongs = Song::where('artist_id', $artist->id)->count();

        // Лайки считаем одним запросом через join
        $totalLikes = DB::table('user_liked_songs')
            ->join('songs', 'songs.id', '=', 'user_liked_songs.song_id')
            ->where('songs.artist_id', $artist->id)
            ->count();

        // Реальные подписчики (users <-> artists через pivot artist_user)
        $followers = $artist->followers()->count();

        // Доход пока не рассчитываем — держим 0 (можно заменить позже на реальную монетизацию)
        $revenue = '$0';

        return response()->json([
            'total_plays' => $totalPlays,
            'monthly_listeners' => $monthlyListeners,
            'total_songs' => $totalSongs,
            'revenue' => $revenue,
            'total_likes' => $totalLikes,
            'followers' => $followers,
        ]);
    }

    /**
     * Получить треки артиста со статусом модерации
     * GET /api/artist/dashboard/songs
     */
    public function getSongs(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'artist') {
            return response()->json(['error' => 'Доступ запрещен. Только для артистов.'], 403);
        }

        $artist = Artist::where('name', $user->name)->first();
        if (!$artist) {
            return response()->json(['data' => []]);
        }

        // Eloquent withCount делает подсчёты через подзапросы (без N+1)
        $songs = Song::where('artist_id', $artist->id)
            ->withCount([
                'likedByUsers as likes_count',
                'listeningHistory as plays_count',
            ])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($song) {
                return [
                    'id' => $song->id,
                    'title' => $song->title,
                    'plays' => (int)($song->plays_count ?? 0),
                    'likes' => (int)($song->likes_count ?? 0),
                    'duration' => $song->duration ?? '0:00',
                    'cover' => $this->getCoverUrl($song->cover_url),
                    'release_date' => $song->created_at ? $song->created_at->format('Y') : date('Y'),
                    'status' => $song->status ?? 'pending',
                    'rejection_reason' => $song->rejection_reason ?? null,
                ];
            });

        return response()->json(['data' => $songs]);
    }

    /**
     * Аналитика: прослушивания по дням (последние 7 дней) + география
     * GET /api/artist/dashboard/analytics
     */
    public function getAnalytics(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'artist') {
            return response()->json(['error' => 'Доступ запрещен. Только для артистов.'], 403);
        }

        $artist = Artist::where('name', $user->name)->first();
        if (!$artist) {
            return response()->json(['daily_plays' => [], 'geography' => []]);
        }

        $daysOfWeek = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
        $from = Carbon::now()->subDays(6)->startOfDay();
        $to = Carbon::now()->endOfDay();

        // Один запрос: группируем по дате
        $playsByDate = ListeningHistory::query()
            ->whereBetween('played_at', [$from, $to])
            ->whereHas('song', function ($q) use ($artist) {
                $q->where('artist_id', $artist->id);
            })
            ->selectRaw('DATE(played_at) as d, COUNT(*) as plays')
            ->groupBy('d')
            ->pluck('plays', 'd');

        $dailyPlays = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $key = $date->format('Y-m-d');
            $dailyPlays[] = [
                'day' => $daysOfWeek[$date->dayOfWeek === 0 ? 6 : $date->dayOfWeek - 1],
                'plays' => (int)($playsByDate[$key] ?? 0),
                'date' => $key,
            ];
        }

        // Для географии оставляем текущую логику, но тоже без pluck song_id
        $geography = $this->getGeographyByArtist($artist);

        return response()->json([
            'daily_plays' => $dailyPlays,
            'geography' => $geography,
        ]);
    }

    /**
     * Инфо об артисте (имя + аватарка) для сайдбара
     * GET /api/artist/dashboard/info
     */
    public function getInfo(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'artist') {
            return response()->json(['error' => 'Доступ запрещен. Только для артистов.'], 403);
        }

        $artist = Artist::where('name', $user->name)->first();

        return response()->json([
            'name' => $user->name,
            'avatar' => $user->avatar_url ?? null,
            'artist_id' => $artist?->id,
            'cover' => $artist ? $this->getCoverUrl($artist->cover_url) : null,
        ]);
    }

    private function getGeographyByArtist(Artist $artist)
    {
        $listenerIds = ListeningHistory::query()
            ->whereHas('song', function ($q) use ($artist) {
                $q->where('artist_id', $artist->id);
            })
            ->distinct('user_id')
            ->pluck('user_id');

        $total = $listenerIds->count();
        if ($total === 0) {
            return [
                ['country' => 'Казахстан', 'percentage' => 0, 'listeners' => '0'],
                ['country' => 'Россия', 'percentage' => 0, 'listeners' => '0'],
                ['country' => 'Узбекистан', 'percentage' => 0, 'listeners' => '0'],
            ];
        }

        // Пока в users нет поля country — делаем детерминированное распределение 65/25/10
        $kazakhstan = (int) round($total * 0.65);
        $russia = (int) round($total * 0.25);
        $uzbekistan = max(0, $total - $kazakhstan - $russia);

        return [
            [
                'country' => 'Казахстан',
                'percentage' => (int) round(($kazakhstan / $total) * 100),
                'listeners' => number_format($kazakhstan, 0, ',', ' '),
            ],
            [
                'country' => 'Россия',
                'percentage' => (int) round(($russia / $total) * 100),
                'listeners' => number_format($russia, 0, ',', ' '),
            ],
            [
                'country' => 'Узбекистан',
                'percentage' => (int) round(($uzbekistan / $total) * 100),
                'listeners' => number_format($uzbekistan, 0, ',', ' '),
            ],
        ];
    }

    private function getCoverUrl($url)
    {
        if (!$url) return 'https://via.placeholder.com/300';
        if (str_starts_with($url, 'http')) return $url;
        return url('storage/' . $url);
    }
}


