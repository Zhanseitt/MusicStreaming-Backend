<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot artist_user ранее был создан пустым (id + timestamps).
     * Для подписок нужны user_id + artist_id + уникальность пары.
     *
     * Делаем максимально совместимо с SQLite (мягкие проверки Schema::hasColumn + try/catch).
     */
    public function up(): void
    {
        if (!Schema::hasTable('artist_user')) {
            Schema::create('artist_user', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('artist_id')->index();
                $table->timestamps();
                $table->unique(['user_id', 'artist_id']);
            });
            return;
        }

        Schema::table('artist_user', function (Blueprint $table) {
            if (!Schema::hasColumn('artist_user', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            }
            if (!Schema::hasColumn('artist_user', 'artist_id')) {
                $table->unsignedBigInteger('artist_id')->nullable()->index();
            }
        });

        // Пытаемся добавить уникальный индекс, если его нет
        try {
            Schema::table('artist_user', function (Blueprint $table) {
                $table->unique(['user_id', 'artist_id']);
            });
        } catch (\Throwable $e) {
            // no-op
        }
    }

    public function down(): void
    {
        // Мягкий откат
        if (!Schema::hasTable('artist_user')) {
            return;
        }
        try {
            Schema::table('artist_user', function (Blueprint $table) {
                try { $table->dropColumn('user_id'); } catch (\Throwable $e) {}
                try { $table->dropColumn('artist_id'); } catch (\Throwable $e) {}
            });
        } catch (\Throwable $e) {
            // no-op
        }
    }
};


