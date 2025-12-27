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
        'album',
        'duration',
        'cover_url',
        'audio_url',
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
}
