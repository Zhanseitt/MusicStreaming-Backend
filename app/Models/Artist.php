<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Artist extends Model
{
    protected $fillable = ['name', 'bio', 'cover_url', 'genre'];

    public function songs()
    {
        return $this->hasMany(Song::class);
    }
}
