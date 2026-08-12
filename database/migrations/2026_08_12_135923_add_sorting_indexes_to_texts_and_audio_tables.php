<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the admin lists that sort by a completion timestamp: without
 * them MySQL filesorts the whole table to return ten rows.
 *
 * The texts index extends the existing (is_main, file_id) one with the sort
 * column, so the dataset filter and the ordering are served by a single index;
 * the old two-column index becomes a redundant prefix of it and is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('texts', function (Blueprint $table) {
            $table->index(['is_main', 'file_id', 'edit_finished_at']);
            $table->dropIndex(['is_main', 'file_id']);
        });

        Schema::table('audio', function (Blueprint $table) {
            $table->index('speak_finished_at');
            $table->index('moderator_finished_at');
        });
    }

    public function down(): void
    {
        Schema::table('texts', function (Blueprint $table) {
            $table->index(['is_main', 'file_id']);
            $table->dropIndex(['is_main', 'file_id', 'edit_finished_at']);
        });

        Schema::table('audio', function (Blueprint $table) {
            $table->dropIndex(['speak_finished_at']);
            $table->dropIndex(['moderator_finished_at']);
        });
    }
};
