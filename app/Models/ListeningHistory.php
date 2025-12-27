<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListeningHistory extends Model
{
    protected $table = 'listening_history';
    public $timestamps = false;  // У нас только played_at
    
    protected $fillable = [
        'user_id', 
        'song_id', 
        'played_at'  // изменили с duration_seconds
    ];

    protected $casts = [
        'played_at' => 'datetime'
    ];

    public function song()
    {
        return $this->belongsTo(Song::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
