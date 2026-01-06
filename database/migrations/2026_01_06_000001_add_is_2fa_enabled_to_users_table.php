<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Добавляем флаг включения 2FA (демо) для всех ролей.
     */
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        if (Schema::hasColumn('users', 'is_2fa_enabled')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_2fa_enabled')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        if (!Schema::hasColumn('users', 'is_2fa_enabled')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_2fa_enabled');
        });
    }
};


