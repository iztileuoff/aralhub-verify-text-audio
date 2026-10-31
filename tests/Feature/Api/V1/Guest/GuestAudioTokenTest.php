<?php

use App\Models\Audio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    config(['dataset.duration_token' => 'duration-secret']);

    $this->audio = Audio::factory()->create([
        'edit_audio_filename' => 'audio/sample.wav',
        'edit_converted_audio_filename' => null,
        'edit_converted_audio_duration' => null,
    ]);
});

/**
 * Both endpoints the external duration script calls, each with a valid body.
 *
 * @return array<string, array{0: string, 1: Closure(): array<string, mixed>}>
 */
function durationEndpoints(): array
{
    return [
        'upload' => ['/api/v1/guest/audio/upload', fn (): array => [
            'audio' => UploadedFile::fake()->create('sample.wav', 4, 'audio/x-wav'),
            'duration' => 48000,
        ]],
        'update' => ['/api/v1/guest/audio/update', fn (): array => [
            'audio_filename' => 'sample.wav',
            'duration' => 48000,
        ]],
    ];
}

it('rejects a call without the duration token', function (string $url, Closure $body) {
    $this->postJson($url, $body())->assertUnauthorized();

    expect($this->audio->fresh()->edit_converted_audio_duration)->toBeNull();
})->with(durationEndpoints());

it('rejects a call with a wrong duration token', function (string $url, Closure $body) {
    $this->withHeader('X-Duration-Token', 'guessed')->postJson($url, $body())->assertUnauthorized();

    expect($this->audio->fresh()->edit_converted_audio_duration)->toBeNull();
})->with(durationEndpoints());

it('stays closed while no token is configured', function (string $url, Closure $body) {
    config(['dataset.duration_token' => null]);

    $this->withHeader('X-Duration-Token', '')->postJson($url, $body())->assertUnauthorized();

    expect($this->audio->fresh()->edit_converted_audio_duration)->toBeNull();
})->with(durationEndpoints());

it('accepts the duration script with the right token', function (string $url, Closure $body) {
    $this->withHeader('X-Duration-Token', 'duration-secret')->postJson($url, $body())->assertSuccessful();

    expect($this->audio->fresh()->edit_converted_audio_duration)->toBe(48000);
})->with(durationEndpoints());
