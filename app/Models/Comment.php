<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $fillable = ['user_id', 'song_id', 'content', 'type'];
    // Важно: подгружаем автора комментария
    protected $with = ['user']; 

    public function user() { return $this->belongsTo(User::class); }
}