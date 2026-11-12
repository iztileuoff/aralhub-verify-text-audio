<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The admin texts list (newest edits first) and the error texts list
     * sorted 70k–140k rows on every open (≈1 s and up to 2 s on prod).
     */
    public function up(): void
    {
        Schema::table('texts', function (Blueprint $table) {
            $table->index(['is_split_part', 'edit_finished_at']);
            $table->index(['has_text_error', 'text_error_reported_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('texts', function (Blueprint $table) {
            $table->dropIndex(['is_split_part', 'edit_finished_at']);
            $table->dropIndex(['has_text_error', 'text_error_reported_at']);
        });
    }
};
