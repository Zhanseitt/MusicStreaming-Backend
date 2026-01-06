<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot-таблицы жанров были созданы "пустыми" (только id/timestamps).
     * Для связей Song<->Genre и Track<->Genre нам нужны FK-колонки.
     *
     * ВАЖНО: делаем миграцию максимально совместимой с SQLite (мягкие проверки Schema::hasColumn
     * + try/catch на операциях, которые могут быть недоступны без doctrine/dbal).
     */
    public function up(): void
    {
        // ===== genre_song =====
        if (!Schema::hasTable('genre_song')) {
            Schema::create('genre_song', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('genre_id')->index();
                $table->unsignedBigInteger('song_id')->index();
                $table->timestamps();
                $table->unique(['genre_id', 'song_id']);
            });
        } else {
            Schema::table('genre_song', function (Blueprint $table) {
                if (!Schema::hasColumn('genre_song', 'genre_id')) {
                    $table->unsignedBigInteger('genre_id')->nullable()->index();
                }
                if (!Schema::hasColumn('genre_song', 'song_id')) {
                    $table->unsignedBigInteger('song_id')->nullable()->index();
                }
            });

            // Пробуем добавить уникальность (если ещё нет) — в SQLite/без dbal может упасть.
            try {
                Schema::table('genre_song', function (Blueprint $table) {
                    $table->unique(['genre_id', 'song_id']);
                });
            } catch (\Throwable $e) {
                // no-op
            }
        }

        // ===== genre_track =====
        if (!Schema::hasTable('genre_track')) {
            Schema::create('genre_track', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('genre_id')->index();
                $table->unsignedBigInteger('track_id')->index();
                $table->timestamps();
                $table->unique(['genre_id', 'track_id']);
            });
        } else {
            Schema::table('genre_track', function (Blueprint $table) {
                if (!Schema::hasColumn('genre_track', 'genre_id')) {
                    $table->unsignedBigInteger('genre_id')->nullable()->index();
                }
                if (!Schema::hasColumn('genre_track', 'track_id')) {
                    $table->unsignedBigInteger('track_id')->nullable()->index();
                }
            });

            try {
                Schema::table('genre_track', function (Blueprint $table) {
                    $table->unique(['genre_id', 'track_id']);
                });
            } catch (\Throwable $e) {
                // no-op
            }
        }
    }

    public function down(): void
    {
        // Откат делаем "мягким" — чтобы не ломать окружения без поддержки dropIndex/dropColumn.
        foreach (['genre_song', 'genre_track'] as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            try {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    foreach (['genre_id', 'song_id', 'track_id'] as $col) {
                        if (Schema::hasColumn($tableName, $col)) {
                            try {
                                $table->dropColumn($col);
                            } catch (\Throwable $e) {
                                // no-op
                            }
                        }
                    }
                });
            } catch (\Throwable $e) {
                // no-op
            }
        }
    }
};


