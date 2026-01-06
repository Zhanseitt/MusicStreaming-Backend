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
        Schema::table('artists', function (Blueprint $table) {
            if (!Schema::hasColumn('artists', 'genre')) {
                // Важно: в ранней версии таблицы artists колонки bio не было,
                // поэтому after('bio') мог ломать миграции (особенно не в SQLite).
                $afterColumn = Schema::hasColumn('artists', 'cover_url') ? 'cover_url' : 'name';
                $table->string('genre')->nullable()->after($afterColumn);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            if (Schema::hasColumn('artists', 'genre')) {
                $table->dropColumn('genre');
            }
        });
    }
};
