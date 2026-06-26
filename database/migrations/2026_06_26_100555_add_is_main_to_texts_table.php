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
        if (Schema::hasColumn('texts', 'is_main')) {
            return;
        }

        Schema::table('texts', function (Blueprint $table) {
            $table->boolean('is_main')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('texts', 'is_main')) {
            return;
        }

        Schema::table('texts', function (Blueprint $table) {
            $table->dropColumn('is_main');
        });
    }
};
