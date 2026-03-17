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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('role');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone')->unique();
            $table->string('password');
            $table->string('gender');
            $table->unsignedTinyInteger('age')->nullable();
            $table->foreignId('specialization_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('course')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(false);
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};
