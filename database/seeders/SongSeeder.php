<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SongSeeder extends Seeder
{
    public function run(): void
    {
        // Создаём или находим артиста
        $artistId = DB::table('artists')->where('name', 'The Weeknd')->value('id');

        if (!$artistId) {
            $artistId = DB::table('artists')->insertGetId([
                'name' => 'The Weeknd',
                'cover_url' => 'https://images.unsplash.com/photo-1614613535308-eb5fbd3d2c17?w=300&fit=crop',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Удаляем старые песни
        DB::table('songs')->truncate();

        // Добавляем треки
        DB::table('songs')->insert([
            [
                'title' => 'Blinding Lights',
                'artist_id' => $artistId,
                'album' => 'After Hours',
                'duration' => '3:20',
                'cover_url' => 'https://images.unsplash.com/photo-1614613535308-eb5fbd3d2c17?w=300&fit=crop',
                'audio_url' => 'audio/The Weeknd - Blinding Lights.mp3', // ← ИСПРАВЛЕНО .mp3
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Starboy',
                'artist_id' => $artistId,
                'album' => 'Starboy',
                'duration' => '3:50',
                'cover_url' => 'https://images.unsplash.com/photo-1493225255756-d9584f8606e9?w=300&fit=crop',
                'audio_url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Save Your Tears',
                'artist_id' => $artistId,
                'album' => 'After Hours',
                'duration' => '3:35',
                'cover_url' => 'https://images.unsplash.com/photo-1619983081563-430f63602796?w=300&fit=crop',
                'audio_url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-3.mp3',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Peaches',
                'artist_id' => $artistId,
                'album' => 'Justice',
                'duration' => '3:18',
                'cover_url' => 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=300&fit=crop',
                'audio_url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-4.mp3',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->command->info('✅ Треки добавлены: ' . DB::table('songs')->count());
    }
}
