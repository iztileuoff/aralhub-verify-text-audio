<?php

use App\Models\Audio;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Artisan::call('migrate:fresh');
});

afterEach(function () {
    Artisan::call('migrate');
});

it('backfills exported_at for existing audios that already have a converted duration', function () {
    $withDuration = Audio::factory()->create(['edit_converted_audio_duration' => 300]);
    $withoutDuration = Audio::factory()->create(['edit_converted_audio_duration' => null]);

    // Roll back newest-first until the exported_at column is gone, keeping the audio rows.
    while (Schema::hasColumn('audio', 'exported_at')) {
        Artisan::call('migrate:rollback', ['--step' => 1]);
    }
    expect(Schema::hasColumn('audio', 'exported_at'))->toBeFalse();

    Artisan::call('migrate');

    expect($withDuration->fresh()->exported_at)->not->toBeNull()
        ->and($withoutDuration->fresh()->exported_at)->toBeNull();
});
