<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('text_id')->nullable()->constrained()->nullOnDelete();
            $table->string('edit_audio_filename')->nullable();
            $table->string('edit_converted_audio_filename')->nullable();
            $table->unsignedInteger('edit_converted_audio_duration')->nullable();
            $table->timestamp('speak_started_at')->nullable();
            $table->timestamp('speak_finished_at')->nullable();
            $table->foreignId('edit_speaker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('edit_speaker_gender')->nullable();
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_correct')->nullable();
            $table->timestamp('moderator_started_at')->nullable();
            $table->timestamp('moderator_finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio');
    }
};
