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
            $table->foreignId('export_id')->nullable()->after('exported_at')->constrained('exports')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audio', function (Blueprint $table) {
            $table->dropConstrainedForeignId('export_id');
        });
    }
};
