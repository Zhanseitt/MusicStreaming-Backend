<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            // Добавляем мягко для совместимости (SQLite + существующие БД)
            if (!Schema::hasColumn('songs', 'genre_id')) {
                $table->unsignedBigInteger('genre_id')->nullable()->after('artist_id');
                $table->index('genre_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            if (Schema::hasColumn('songs', 'genre_id')) {
                try {
                    $table->dropColumn('genre_id');
                } catch (\Throwable $e) {
                    // no-op
                }
            }
        });
    }
};


