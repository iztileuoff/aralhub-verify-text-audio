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
        Schema::table('call_recordings', function (Blueprint $table) {
            $table->string('landmark_decision')->nullable()->after('moderated_at');
            $table->foreignId('landmark_id')->nullable()->after('landmark_decision')->constrained('call_landmarks')->nullOnDelete();
            $table->foreignId('landmark_by')->nullable()->after('landmark_id')->constrained('users')->nullOnDelete();
            $table->timestamp('landmark_at')->nullable()->after('landmark_by');
            $table->foreignId('landmark_assigned_to')->nullable()->after('landmark_at')->constrained('users')->nullOnDelete();
            $table->timestamp('landmark_assigned_at')->nullable()->after('landmark_assigned_to');

            $table->index(['landmark_decision', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('call_recordings', function (Blueprint $table) {
            $table->dropIndex(['landmark_decision', 'status']);
            $table->dropConstrainedForeignId('landmark_assigned_to');
            $table->dropConstrainedForeignId('landmark_by');
            $table->dropConstrainedForeignId('landmark_id');
            $table->dropColumn(['landmark_decision', 'landmark_at', 'landmark_assigned_at']);
        });
    }
};
