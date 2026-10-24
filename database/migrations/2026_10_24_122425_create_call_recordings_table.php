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
        Schema::create('call_recordings', function (Blueprint $table) {
            $table->id();
            $table->string('batch')->nullable()->index();
            $table->string('original_filename');
            $table->string('source_path')->unique();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('trimmer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->unsignedInteger('source_duration_ms')->nullable();
            $table->unsignedInteger('trim_start_ms')->nullable();
            $table->unsignedInteger('trim_end_ms')->nullable();
            $table->string('trimmed_path')->nullable();
            $table->unsignedInteger('trimmed_duration')->nullable();
            $table->text('transcript')->nullable();
            $table->text('normalized_transcript')->nullable();
            $table->text('tokenized_transcript')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('rejections_count')->default(0);
            $table->text('reject_reason')->nullable();
            $table->string('skip_reason')->nullable();
            $table->text('skip_comment')->nullable();
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamp('exported_at')->nullable()->index();
            $table->foreignId('export_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'assigned_at']);
            $table->index(['trimmer_id', 'status']);
            $table->index(['status', 'submitted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_recordings');
    }
};
