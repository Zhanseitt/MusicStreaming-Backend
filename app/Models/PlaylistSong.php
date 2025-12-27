<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaylistSong extends Model
{
    protected $fillable = ['user_id', 'name', 'color'];
    public function user() { return $this->belongsTo(User::class); }
    public function songs() { return $this->belongsToMany(Song::class, 'playlist_song'); }
}
