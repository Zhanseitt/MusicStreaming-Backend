<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SongSeeder extends Seeder
{
    public function run(): void
    {
        // Очищаем таблицу перед вставкой
        DB::table('songs')->delete();

        // Твоя ссылка на Cloudflare R2
        $cloudUrl = 'https://pub-3acf2199a3bf432ea1c30b6ee3612f19.r2.dev';

        // Вставляем все данные одним запросом
        DB::table('songs')->insert([
            [
                'title' => 'Asian Lo-Fi', 
                'artist_id' => 4, // Jazz Collective
                'album' => 'Chill Vibes',
                'duration' => '3:15',
                'cover_url' => 'https://images.unsplash.com/photo-1528148343865-51218c074dc6?w=400&fit=crop',
                'audio_url' => "{$cloudUrl}/-asia.mp3", 
                'created_at' => now(), 'updated_at' => now()
            ],
            [
                'title' => 'Retro Future', 
                'artist_id' => 3, // Synthwave Radio
                'album' => 'Neon Nights',
                'duration' => '2:45',
                'cover_url' => 'https://images.unsplash.com/photo-1614613535308-eb5fbd3d2c17?w=400&fit=crop',
                'audio_url' => "{$cloudUrl}/82872.mp3", 
                'created_at' => now(), 'updated_at' => now()
            ],
            [
                'title' => 'Ocean Breeze', 
                'artist_id' => 2, // Coastal Drift
                'album' => 'Blue Horizon',
                'duration' => '3:28',
                'cover_url' => 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=400&fit=crop',
                'audio_url' => "{$cloudUrl}/barradeen.mp3", 
                'created_at' => now(), 'updated_at' => now()
            ],
            [
                'title' => 'Urban Action', 
                'artist_id' => 5, // City Beats
                'album' => 'Metro Sound',
                'duration' => '4:31',
                'cover_url' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=400&fit=crop',
                'audio_url' => "{$cloudUrl}/doug-maxwell.mp3", 
                'created_at' => now(), 'updated_at' => now()
            ],
            [
                'title' => 'Low Horizon', 
                'artist_id' => 4, // Jazz Collective
                'album' => 'The Scope',
                'duration' => '3:55',
                'cover_url' => 'https://images.unsplash.com/photo-1511379938547-c1f69419868d?w=400&fit=crop',
                'audio_url' => "{$cloudUrl}/kai-engel.mp3", 
                'created_at' => now(), 'updated_at' => now()
            ],
            [
                'title' => 'Mislaid', 
                'artist_id' => 1, // Neon Dreams
                'album' => 'Lost & Found',
                'duration' => '4:10',
                'cover_url' => 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=400&fit=crop',
                'audio_url' => "{$cloudUrl}/mislaid.mp3", 
                'created_at' => now(), 'updated_at' => now()
            ],
            [
                'title' => 'Party Starter', 
                'artist_id' => 5, // City Beats
                'album' => 'Club Mix',
                'duration' => '3:40',
                'cover_url' => 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=400&fit=crop',
                'audio_url' => "{$cloudUrl}/rckes-starter.mp3", 
                'created_at' => now(), 'updated_at' => now()
            ],
            [
                'title' => 'Blinding Lights', 
                'artist_id' => 1, // Neon Dreams
                'album' => 'After Hours',
                'duration' => '3:20',
                'cover_url' => 'https://images.unsplash.com/photo-1619983081563-430f63602796?w=400&fit=crop',
                'audio_url' => "{$cloudUrl}/The Weeknd - Blinding Lights.flac", 
                'created_at' => now(), 'updated_at' => now()
            ],
        ]);
    }
}