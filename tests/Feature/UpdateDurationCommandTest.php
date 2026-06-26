<?php

use App\Models\Audio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

afterEach(function () {
    @unlink(storage_path('app/test_result.txt'));
});

it('fills durations by id without overwriting existing ones', function () {
    $toUpdate = Audio::factory()->create([
        'edit_converted_audio_duration' => null,
    ]);

    $existing = Audio::factory()->create([
        'edit_converted_audio_duration' => 500,
    ]);

    file_put_contents(
        storage_path('app/test_result.txt'),
        "{$toUpdate->id};123\n{$existing->id};999\n999999;111\n",
    );

    Artisan::call('update:duration', ['--filename' => 'test_result.txt']);

    expect($toUpdate->fresh()->edit_converted_audio_duration)->toBe(123)
        ->and($existing->fresh()->edit_converted_audio_duration)->toBe(500);
});
