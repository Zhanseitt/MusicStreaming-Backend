<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Playlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 
        'name', 
        'color'
    ];

    // Связь с владельцем
    public function user() 
    { 
        return $this->belongsTo(User::class); 
    }

    // Связь с песнями (Многие ко многим)
    public function songs() 
    { 
        return $this->belongsToMany(Song::class, 'playlist_song');
    }
}
