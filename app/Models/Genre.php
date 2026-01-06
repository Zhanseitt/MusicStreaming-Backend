<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genre extends Model
{
    protected $fillable = ['name'];

    /**
     * Песни, связанные с жанром (many-to-many через genre_song)
     */
    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class, 'genre_song');
    }

    /**
     * Треки (новая сущность Track), связанные с жанром (many-to-many через genre_track)
     */
    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(Track::class, 'genre_track');
    }
}
