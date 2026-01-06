<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Artist extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'bio',
        'cover_url',
        'genre',
    ];

    /**
     * Связь с треками
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }

    /**
     * Связь с альбомами
     */
    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    /**
     * Связь с песнями (для обратной совместимости)
     */
    public function songs(): HasMany
    {
        return $this->hasMany(Song::class);
    }

    /**
     * Подписчики артиста (пользователи)
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'artist_user', 'artist_id', 'user_id')
            ->withTimestamps();
    }
}
