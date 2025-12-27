<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Playlist;
use App\Models\Comment;
use App\Models\ListeningHistory;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['email_verified_at' => 'datetime', 'password' => 'hashed'];

    public function comments() { return $this->hasMany(Comment::class); }
    public function history() { return $this->hasMany(ListeningHistory::class); }
    public function playlists()
    {
        return $this->hasMany(Playlist::class);
    }
}
