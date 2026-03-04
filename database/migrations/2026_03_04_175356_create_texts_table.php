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
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('transcript_id');
            $table->string('audio_filename');
            $table->text('original_transcript');
            $table->text('normalized_transcript');
            $table->text('tokenized_transcript');
            $table->unsignedInteger('duration');
            $table->string('speaker_gender');
            $table->text('filter_transcript')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('texts');
    }
};
