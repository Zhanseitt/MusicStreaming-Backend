<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Лайки
        if (!Schema::hasTable('song_user_likes')) {
            Schema::create('song_user_likes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('song_id')->constrained()->onDelete('cascade');
                $table->timestamps();
                $table->unique(['user_id', 'song_id']);
            });
        }

        // История
        if (!Schema::hasTable('listening_history')) {
            Schema::create('listening_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('song_id')->constrained()->onDelete('cascade');
                $table->timestamp('played_at')->useCurrent();
                $table->index(['user_id', 'played_at']);
            });
        }

        // Плейлисты
        if (!Schema::hasTable('playlists')) {
            Schema::create('playlists', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('cover_url')->nullable();
                $table->timestamps();
            });
        }

        // Связь плейлист-песня
        if (!Schema::hasTable('playlist_song')) {
            Schema::create('playlist_song', function (Blueprint $table) {
                $table->id();
                $table->foreignId('playlist_id')->constrained()->onDelete('cascade');
                $table->foreignId('song_id')->constrained()->onDelete('cascade');
                $table->integer('position')->default(0);
                $table->timestamp('added_at')->useCurrent();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('playlist_song');
        Schema::dropIfExists('playlists');
        Schema::dropIfExists('listening_history');
        Schema::dropIfExists('song_user_likes');
    }
};
