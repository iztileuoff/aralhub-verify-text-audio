<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            // Human-readable name of the dataset ("v3"), used to tell datasets
            // apart in the admin panel and to name their export groups.
            $table->string('label')->nullable()->after('filename');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};
