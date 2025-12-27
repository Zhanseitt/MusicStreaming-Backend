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
        Schema::table('songs', function (Blueprint $table) {
            // JSON с массивом строк текста песни [{time, text}, ...]
            $table->json('lyrics')->nullable()->after('audio_url');

            // если нужен цвет оформления для фронта:
            // $table->string('color')->nullable()->after('lyrics');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->dropColumn('lyrics');
            // $table->dropColumn('color');
        });
    }
};
