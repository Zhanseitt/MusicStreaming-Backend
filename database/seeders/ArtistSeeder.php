<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ArtistSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('artists')->insert([
            ['name' => 'Neon Dreams', 'cover_url' => 'https://i.scdn.co/image/ab6761610000e5eb9e528993a2820267b97f6aae', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Coastal Drift', 'cover_url' => 'https://i.scdn.co/image/ab6761610000e5eb1bc4473b6c9d9073a90c13a1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Synthwave Radio', 'cover_url' => 'https://i.scdn.co/image/ab6761610000e5ebaadc18cac8d48124357c38e6', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Jazz Collective', 'cover_url' => 'https://i.scdn.co/image/ab676161000051744a21b4760d2ecb7b0dcdc8da', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'City Beats', 'cover_url' => 'https://i.scdn.co/image/ab6761610000e5eb714a40f60a09dc498ce6799d', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}