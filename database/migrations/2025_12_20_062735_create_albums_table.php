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
    Schema::create('albums', function (Blueprint $table) {
        $table->id();
        $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
        $table->string('title');
        $table->string('cover_url')->nullable();
        $table->date('release_date')->nullable();
        $table->enum('type', ['album', 'single', 'ep'])->default('album');
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('albums');
    }
};
