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
        Schema::create('playlist_external_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained()->onDelete('cascade');
            $table->string('external_id'); // ID трека из Jamendo (например, "jamendo_123")
            $table->string('title');
            $table->string('artist');
            $table->string('cover')->nullable();
            $table->string('audio_url');
            $table->string('source')->default('jamendo'); // Источник трека
            $table->string('shareurl')->nullable(); // Ссылка на оригинальный трек на Jamendo
            $table->text('tags')->nullable(); // Теги трека
            $table->timestamps();
            
            // Уникальность: один и тот же внешний трек может быть в плейлисте только один раз
            $table->unique(['playlist_id', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playlist_external_tracks');
    }
};
