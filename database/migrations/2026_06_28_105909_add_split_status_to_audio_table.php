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
        Schema::table('audio', function (Blueprint $table) {
            $table->string('split_status')->default('NONE')->index()->after('is_correct');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audio', function (Blueprint $table) {
            $table->dropIndex(['split_status']);
            $table->dropColumn('split_status');
        });
    }
};
