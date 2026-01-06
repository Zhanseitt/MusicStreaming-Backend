<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ArtistSeeder extends Seeder
{
    public function run(): void
    {
        // Жестко задаем ID, чтобы SongSeeder точно знал, к кому вязать песни
        $artists = [
            ['id' => 1, 'name' => 'Neon Dreams', 'cover_url' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4'],
            ['id' => 2, 'name' => 'Coastal Drift', 'cover_url' => 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745'],
            ['id' => 3, 'name' => 'Synthwave Radio', 'cover_url' => 'https://images.unsplash.com/photo-1514320291840-2e0a9bf2a9ae'],
            ['id' => 4, 'name' => 'Jazz Collective', 'cover_url' => 'https://images.unsplash.com/photo-1511192336575-5a79af67a629'],
            ['id' => 5, 'name' => 'City Beats', 'cover_url' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f'],
        ];

        foreach ($artists as $artist) {
            DB::table('artists')->insertOrIgnore(array_merge($artist, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
    }
}