<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Гарантируем наличие artists.bio.
     *
     * Почему отдельная миграция:
     * - В проекте уже были попытки добавлять bio/genre, но в некоторых БД
     *   миграции могли числиться "применёнными", при этом схема осталась старой.
     * - Эта миграция безопасна (idempotent) и исправляет расхождение схемы.
     */
    public function up(): void
    {
        if (!Schema::hasTable('artists')) {
            return;
        }

        if (Schema::hasColumn('artists', 'bio')) {
            return;
        }

        Schema::table('artists', function (Blueprint $table) {
            $table->text('bio')->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('artists')) {
            return;
        }

        if (!Schema::hasColumn('artists', 'bio')) {
            return;
        }

        Schema::table('artists', function (Blueprint $table) {
            $table->dropColumn('bio');
        });
    }
};


