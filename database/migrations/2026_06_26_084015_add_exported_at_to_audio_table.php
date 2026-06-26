<?php

use App\Models\Audio;
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
            $table->timestamp('exported_at')->nullable()->after('edit_converted_audio_duration');
        });

        Audio::query()
            ->whereNotNull('edit_converted_audio_duration')
            ->whereNull('exported_at')
            ->update(['exported_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audio', function (Blueprint $table) {
            $table->dropColumn('exported_at');
        });
    }
};
