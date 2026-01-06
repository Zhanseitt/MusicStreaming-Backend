<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('user_liked_songs')) {
            Schema::create('user_liked_songs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('song_id')->constrained()->onDelete('cascade');
                $table->timestamps();
                $table->unique(['user_id', 'song_id']);
            });
        } else {
            // Если таблица существует, но не имеет нужных полей, добавим их
            Schema::table('user_liked_songs', function (Blueprint $table) {
                if (!Schema::hasColumn('user_liked_songs', 'user_id')) {
                    $table->foreignId('user_id')->constrained()->onDelete('cascade');
                }
                if (!Schema::hasColumn('user_liked_songs', 'song_id')) {
                    $table->foreignId('song_id')->constrained()->onDelete('cascade');
                }
                // Добавляем уникальный индекс, если его нет
                $indexes = Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('user_liked_songs');
                if (!isset($indexes['user_liked_songs_user_id_song_id_unique'])) {
                    $table->unique(['user_id', 'song_id']);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_liked_songs');
    }
};
