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
        $hasBio = Schema::hasColumn('artists', 'bio');
        $hasGenre = Schema::hasColumn('artists', 'genre');

        if ($hasBio && $hasGenre) {
            return;
        }

        Schema::table('artists', function (Blueprint $table) use ($hasBio, $hasGenre) {
            if (!$hasBio) {
                $table->text('bio')->nullable();
            }
            if (!$hasGenre) {
                $table->string('genre')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasBio = Schema::hasColumn('artists', 'bio');
        $hasGenre = Schema::hasColumn('artists', 'genre');

        if (!$hasBio && !$hasGenre) {
            return;
        }

        Schema::table('artists', function (Blueprint $table) use ($hasBio, $hasGenre) {
            $columns = [];
            if ($hasBio) {
                $columns[] = 'bio';
            }
            if ($hasGenre) {
                $columns[] = 'genre';
            }

            $table->dropColumn($columns);
        });
    }
};


