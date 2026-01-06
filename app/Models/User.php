<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Playlist;
use App\Models\Comment;
use App\Models\ListeningHistory;
use App\Models\Song;
use App\Models\Artist;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'is_banned', 'is_artist_requested', 'ban_reason', 'is_2fa_enabled'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_2fa_enabled' => 'boolean'];

    public function comments() { return $this->hasMany(Comment::class); }
    public function history() { return $this->hasMany(ListeningHistory::class); }
    public function playlists()
    {
        return $this->hasMany(Playlist::class);
    }
    
    // Лайки песен
    public function likedSongs()
    {
        return $this->belongsToMany(Song::class, 'user_liked_songs', 'user_id', 'song_id');
    }

    /**
     * Артисты, на которых подписан пользователь (многие-ко-многим через artist_user)
     */
    public function followedArtists()
    {
        return $this->belongsToMany(Artist::class, 'artist_user', 'user_id', 'artist_id')
            ->withTimestamps();
    }
}
