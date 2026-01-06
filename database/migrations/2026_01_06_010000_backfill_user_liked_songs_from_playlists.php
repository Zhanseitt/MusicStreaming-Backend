<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Бэкфилл лайков: исторически "лайк" мог храниться только через плейлист
     * "Понравившиеся" / "Любимые" (pivot playlist_song).
     *
     * Для корректной статистики артистов нам нужен user_liked_songs.
     */
    public function up(): void
    {
        if (
            !Schema::hasTable('playlists') ||
            !Schema::hasTable('playlist_song') ||
            !Schema::hasTable('user_liked_songs')
        ) {
            return;
        }

        $rows = DB::table('playlist_song')
            ->join('playlists', 'playlists.id', '=', 'playlist_song.playlist_id')
            ->whereIn('playlists.name', ['Понравившиеся', 'Любимые'])
            ->select('playlists.user_id as user_id', 'playlist_song.song_id as song_id')
            ->distinct()
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $now = now();
        foreach ($rows->chunk(1000) as $chunk) {
            DB::table('user_liked_songs')->insertOrIgnore(
                $chunk->map(fn ($r) => [
                    'user_id' => $r->user_id,
                    'song_id' => $r->song_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all()
            );
        }
    }

    public function down(): void
    {
        // no-op: не удаляем, чтобы не потерять реальные лайки
    }
};


