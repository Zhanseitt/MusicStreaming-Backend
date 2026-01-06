<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('song_requests', function (Blueprint $table) {
            // Важно: миграция-фикс для проектов, где song_requests уже создана (id + timestamps),
            // а затем была расширена логика заявок. Добавляем поля "мягко" (nullable),
            // чтобы не ломать существующие БД (особенно SQLite).

            if (!Schema::hasColumn('song_requests', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            }

            if (!Schema::hasColumn('song_requests', 'title')) {
                $table->string('title')->nullable();
            }

            if (!Schema::hasColumn('song_requests', 'genre')) {
                $table->string('genre')->nullable();
            }

            if (!Schema::hasColumn('song_requests', 'authors')) {
                $table->text('authors')->nullable();
            }

            if (!Schema::hasColumn('song_requests', 'lyrics')) {
                $table->text('lyrics')->nullable();
            }

            if (!Schema::hasColumn('song_requests', 'description')) {
                $table->text('description')->nullable();
            }

            if (!Schema::hasColumn('song_requests', 'social_links')) {
                $table->string('social_links')->nullable();
            }

            if (!Schema::hasColumn('song_requests', 'audio_path')) {
                $table->string('audio_path')->nullable();
            }

            if (!Schema::hasColumn('song_requests', 'cover_path')) {
                $table->string('cover_path')->nullable();
            }

            if (!Schema::hasColumn('song_requests', 'doc_path')) {
                $table->string('doc_path')->nullable();
            }

            if (!Schema::hasColumn('song_requests', 'status')) {
                $table->string('status')->default('pending')->index();
            }

            if (!Schema::hasColumn('song_requests', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('song_requests', function (Blueprint $table) {
            $columns = [
                'user_id',
                'title',
                'genre',
                'authors',
                'lyrics',
                'description',
                'social_links',
                'audio_path',
                'cover_path',
                'doc_path',
                'status',
                'rejection_reason',
            ];

            foreach ($columns as $col) {
                if (Schema::hasColumn('song_requests', $col)) {
                    // Для SQLite dropColumn может требовать doctrine/dbal, но у нас это migration-фикс.
                    // Если не поддерживается, лучше откат не делать, чем ломать окружение.
                    try {
                        $table->dropColumn($col);
                    } catch (\Throwable $e) {
                        // no-op
                    }
                }
            }
        });
    }
};


