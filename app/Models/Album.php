<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Album extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'artist_id',
        'cover_url',
        'release_date',
        'type',
    ];

    protected $casts = [
        'release_date' => 'date',
    ];

    /**
     * Связь с артистом
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * Связь с треками
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }
}
