<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('texts', function (Blueprint $table) {
            $table->index(['is_main', 'file_id']);
            $table->index(['edit_speaker_id', 'speak_finished_at']);
            $table->index(['edit_user_id', 'edit_finished_at']);
            $table->index(['moderator_id', 'moderator_finished_at']);
        });

        Schema::table('audio', function (Blueprint $table) {
            $table->index(['edit_speaker_id', 'speak_finished_at']);
            $table->index(['moderator_id', 'moderator_finished_at']);
        });
    }

    public function down(): void
    {
        Schema::table('texts', function (Blueprint $table) {
            $table->dropIndex(['is_main', 'file_id']);
            $table->dropIndex(['edit_speaker_id', 'speak_finished_at']);
            $table->dropIndex(['edit_user_id', 'edit_finished_at']);
            $table->dropIndex(['moderator_id', 'moderator_finished_at']);
        });

        Schema::table('audio', function (Blueprint $table) {
            $table->dropIndex(['edit_speaker_id', 'speak_finished_at']);
            $table->dropIndex(['moderator_id', 'moderator_finished_at']);
        });
    }
};
