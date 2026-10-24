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
        Schema::create('call_recording_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_recording_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('trimmer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->unsignedInteger('trimmed_duration')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'trimmer_id']);
            $table->index(['trimmer_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_recording_events');
    }
};
