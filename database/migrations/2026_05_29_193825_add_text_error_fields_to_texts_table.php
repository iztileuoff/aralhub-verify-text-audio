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
        Schema::table('texts', function (Blueprint $table) {
            $table->boolean('has_text_error')->default(false);
            $table->foreignId('text_error_reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('text_error_reported_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('texts', function (Blueprint $table) {
            $table->dropForeign(['text_error_reported_by']);
            $table->dropColumn(['has_text_error', 'text_error_reported_by', 'text_error_reported_at']);
        });
    }
};
