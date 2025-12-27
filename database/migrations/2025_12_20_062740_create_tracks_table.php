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
    Schema::create('tracks', function (Blueprint $table) {
        $table->id();
        $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
        $table->foreignId('album_id')->nullable()->constrained()->cascadeOnDelete();
        $table->string('title');
        $table->string('file_path'); // Путь к файлу (локально или S3)
        $table->integer('duration')->default(0); // В секундах
        $table->integer('bpm')->nullable();
        $table->text('lyrics')->nullable();
        $table->unsignedBigInteger('listens_count')->default(0);
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracks');
    }
};
