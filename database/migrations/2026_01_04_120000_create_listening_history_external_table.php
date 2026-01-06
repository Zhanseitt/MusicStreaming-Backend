<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('listening_history_external')) {
            Schema::create('listening_history_external', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('external_id'); // ID трека из Jamendo (например, "123")
                $table->string('title');
                $table->string('artist');
                $table->string('cover')->nullable();
                $table->string('audio_url');
                $table->string('source')->default('jamendo'); // Источник: jamendo, spotify и т.д.
                $table->string('shareurl')->nullable();
                $table->text('tags')->nullable();
                $table->string('duration')->default('0:00');
                $table->timestamp('played_at')->useCurrent();
                $table->integer('listened_seconds')->default(0);
                $table->index(['user_id', 'played_at']);
                $table->index(['external_id', 'source']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('listening_history_external');
    }
};

