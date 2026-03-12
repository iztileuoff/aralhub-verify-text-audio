<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('texts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('transcript_id')->nullable();
            $table->string('audio_filename')->nullable();
            $table->text('original_transcript')->nullable();
            $table->text('normalized_transcript')->nullable();
            $table->text('tokenized_transcript')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->string('speaker_gender')->nullable();
            $table->text('filter_original_transcript')->nullable();
            $table->text('filter_normalized_transcript')->nullable();
            $table->text('filter_tokenized_transcript')->nullable();
            $table->text('edit_original_transcript')->nullable();
            $table->text('edit_normalized_transcript')->nullable();
            $table->text('edit_tokenized_transcript')->nullable();
            $table->foreignId('edit_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('edit_started_at')->nullable();
            $table->timestamp('edit_finished_at')->nullable();
            $table->foreignId('edit_cancelled_user_id')->nullable()->constrained('users')->nullOnDelete();
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
        Schema::dropIfExists('texts');
    }
};
