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
        Schema::table('listening_history', function (Blueprint $table) {
            $table->integer('listened_seconds')->default(0)->after('played_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listening_history', function (Blueprint $table) {
            $table->dropColumn('listened_seconds');
        });
    }
};
