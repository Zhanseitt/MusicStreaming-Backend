<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Track extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'artist_id',
        'album_id',
        'file_path',
        'duration',
        'bpm',
        'lyrics',
        'listens_count',
        'cover_url',
        'audio_url',
    ];

    protected $casts = [
        'duration' => 'integer',
        'bpm' => 'integer',
        'listens_count' => 'integer',
        'lyrics' => 'array',
    ];

    /**
     * Связь с артистом
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * Связь с альбомом
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    /**
     * Связь с пользователями, которые лайкнули трек
     */
    public function likedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_liked_songs', 'song_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Увеличить счетчик прослушиваний
     */
    public function incrementListens(): void
    {
        $this->increment('listens_count');
    }
}
