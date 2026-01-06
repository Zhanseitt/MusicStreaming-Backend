<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('song_requests', function (Blueprint $table) {
            // Добавляем мягко для совместимости с SQLite
            if (!Schema::hasColumn('song_requests', 'genre_id')) {
                $table->unsignedBigInteger('genre_id')->nullable()->after('genre');
                $table->index('genre_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('song_requests', function (Blueprint $table) {
            if (Schema::hasColumn('song_requests', 'genre_id')) {
                try {
                    $table->dropColumn('genre_id');
                } catch (\Throwable $e) {
                    // no-op
                }
            }
        });
    }
};


