<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SongRequest extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'genre',
        'genre_id',
        'authors',
        'lyrics',
        'description',
        'social_links',
        'audio_path',
        'cover_path',
        'doc_path',
        'status',
        'rejection_reason',
    ];

    protected $casts = [
        'lyrics' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
