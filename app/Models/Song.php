<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Song extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'artist_id',
        'genre_id',
        'album',
        'duration',
        'cover_url',
        'audio_url',
        'status',
        'rejection_reason',
    ];

    protected $casts = [
        'lyrics' => 'array',
    ];

    // Связь с таблицей ARTISTS (не users!)
    public function artist()
    {
        return $this->belongsTo(Artist::class, 'artist_id');
    }

    // Лайки
    public function likedByUsers()
    {
        return $this->belongsToMany(User::class, 'user_liked_songs');
    }

    // История прослушиваний (таблица listening_history)
    public function listeningHistory()
    {
        return $this->hasMany(ListeningHistory::class, 'song_id');
    }

    // Жанры
    public function genres()
    {
        return $this->belongsToMany(Genre::class, 'genre_song');
    }
}
