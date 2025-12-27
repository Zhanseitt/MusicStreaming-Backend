<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->integer('play_count')->default(0)->after('audio_url');
            $table->string('country')->nullable()->after('play_count');
            $table->string('genre')->nullable()->after('country');
            $table->decimal('rating', 3, 2)->default(0)->after('genre');
        });
    }

    public function down()
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->dropColumn(['play_count', 'country', 'genre', 'rating']);
        });
    }
};
