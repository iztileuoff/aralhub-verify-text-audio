<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stamp every new recording with the disk it was written to, so the object
     * storage can be changed without moving the files already uploaded.
     * NULL means "recorded before the switch" and resolves to audio.legacy_disk.
     */
    public function up(): void
    {
        Schema::table('audio', function (Blueprint $table) {
            $table->string('storage_disk')->nullable()->after('edit_audio_filename');
        });

        Schema::table('texts', function (Blueprint $table) {
            $table->string('storage_disk')->nullable()->after('edit_audio_filename');
        });
    }

    public function down(): void
    {
        Schema::table('audio', function (Blueprint $table) {
            $table->dropColumn('storage_disk');
        });

        Schema::table('texts', function (Blueprint $table) {
            $table->dropColumn('storage_disk');
        });
    }
};
