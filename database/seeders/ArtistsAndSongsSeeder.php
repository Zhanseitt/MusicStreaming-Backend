<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Artist;
use App\Models\Song;

class ArtistsAndSongsSeeder extends Seeder
{
    public function run()
    {
        // Создаём артистов
        $weeknd = Artist::firstOrCreate(
            ['name' => 'The Weeknd'],
            [
                'cover_url' => 'https://images.unsplash.com/photo-1614613535308-eb5fbd3d2c17?q=80&w=300',
                'bio' => 'Canadian singer and songwriter',
                'genre' => 'R&B'
            ]
        );

        $duaLipa = Artist::firstOrCreate(
            ['name' => 'Dua Lipa'],
            [
                'cover_url' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?q=80&w=300',
                'bio' => 'British pop singer',
                'genre' => 'Pop'
            ]
        );

        $justinBieber = Artist::firstOrCreate(
            ['name' => 'Justin Bieber'],
            [
                'cover_url' => 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?q=80&w=300',
                'bio' => 'Canadian pop star',
                'genre' => 'Pop'
            ]
        );

        $olivia = Artist::firstOrCreate(
            ['name' => 'Olivia Rodrigo'],
            [
                'cover_url' => 'https://images.unsplash.com/photo-1514525253440-b393452e8d26?q=80&w=300',
                'bio' => 'American singer-songwriter',
                'genre' => 'Pop'
            ]
        );

        // Создаём песни
        Song::firstOrCreate(
            ['title' => 'Blinding Lights'],
            [
                'artist_id' => $weeknd->id,
                'album' => 'After Hours',
                'duration' => '3:20',
                'cover_url' => 'https://images.unsplash.com/photo-1614613535308-eb5fbd3d2c17?q=80&w=300',
                'audio_url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3',
                'play_count' => 250000,
                'country' => 'US',
                'genre' => 'pop',
                'rating' => 4.8
            ]
        );

        Song::firstOrCreate(
            ['title' => 'Save Your Tears'],
            [
                'artist_id' => $weeknd->id,
                'album' => 'After Hours',
                'duration' => '3:35',
                'cover_url' => 'https://images.unsplash.com/photo-1619983081563-430f63602796?q=80&w=300',
                'audio_url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3',
                'play_count' => 180000,
                'country' => 'US',
                'genre' => 'pop',
                'rating' => 4.6
            ]
        );

        Song::firstOrCreate(
            ['title' => 'Levitating'],
            [
                'artist_id' => $duaLipa->id,
                'album' => 'Future Nostalgia',
                'duration' => '3:23',
                'cover_url' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?q=80&w=300',
                'audio_url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-3.mp3',
                'play_count' => 220000,
                'country' => 'UK',
                'genre' => 'pop',
                'rating' => 4.7
            ]
        );

        Song::firstOrCreate(
            ['title' => 'Peaches'],
            [
                'artist_id' => $justinBieber->id,
                'album' => 'Justice',
                'duration' => '3:18',
                'cover_url' => 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?q=80&w=300',
                'audio_url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-4.mp3',
                'play_count' => 200000,
                'country' => 'US',
                'genre' => 'pop',
                'rating' => 4.5
            ]
        );

        Song::firstOrCreate(
            ['title' => 'good 4 u'],
            [
                'artist_id' => $olivia->id,
                'album' => 'SOUR',
                'duration' => '2:58',
                'cover_url' => 'https://images.unsplash.com/photo-1514525253440-b393452e8d26?q=80&w=300',
                'audio_url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-5.mp3',
                'play_count' => 190000,
                'country' => 'US',
                'genre' => 'pop',
                'rating' => 4.9
            ]
        );

        Song::firstOrCreate(
            ['title' => 'drivers license'],
            [
                'artist_id' => $olivia->id,
                'album' => 'SOUR',
                'duration' => '4:02',
                'cover_url' => 'https://images.unsplash.com/photo-1514525253440-b393452e8d26?q=80&w=300',
                'audio_url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-6.mp3',
                'play_count' => 280000,
                'country' => 'US',
                'genre' => 'pop',
                'rating' => 4.8
            ]
        );

        // Hidden Gems
        Song::firstOrCreate(
            ['title' => 'Midnight Dreams'],
            [
                'artist_id' => $weeknd->id,
                'album' => 'Unreleased',
                'duration' => '3:45',
                'cover_url' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?q=80&w=300',
                'audio_url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-7.mp3',
                'play_count' => 5000,
                'country' => 'US',
                'genre' => 'pop',
                'rating' => 4.9
            ]
        );

        Song::firstOrCreate(
            ['title' => 'Hidden Treasure'],
            [
                'artist_id' => $duaLipa->id,
                'album' => 'B-Sides',
                'duration' => '3:10',
                'cover_url' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?q=80&w=300',
                'audio_url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-8.mp3',
                'play_count' => 3000,
                'country' => 'UK',
                'genre' => 'pop',
                'rating' => 4.8
            ]
        );

        $this->command->info('✅ Artists and Songs seeded successfully!');
    }
}
