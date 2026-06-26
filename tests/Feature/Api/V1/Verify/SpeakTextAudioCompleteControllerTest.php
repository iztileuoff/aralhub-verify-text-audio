<?php

use App\Enums\GenderEnum;
use App\Enums\RoleEnum;
use App\Models\Text;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'role' => RoleEnum::SPEAKER->value,
        'gender' => GenderEnum::MALE->value,
    ]);

    Sanctum::actingAs($this->user);
});

it('stores the audio and updates the text when the upload succeeds', function () {
    Storage::fake('yandex-s3');

    $text = Text::factory()->create([
        'speak_started_at' => now()->subMinutes(5),
        'edit_speaker_id' => $this->user->id,
    ]);

    $response = $this->postJson(
        route('admin.verify.speak.text.audio.complete', $text),
        ['audio' => UploadedFile::fake()->create('audio.mp3', 100, 'audio/mpeg')]
    );

    $response->assertSuccessful();

    $audio = $text->audio()->sole();

    expect($audio->edit_speaker_id)->toBe($this->user->id)
        ->and($audio->edit_speaker_gender)->toBe(GenderEnum::MALE)
        ->and($audio->edit_audio_filename)->toStartWith('audio/');

    Storage::disk('yandex-s3')->assertExists($audio->edit_audio_filename);

    $text->refresh();

    expect($text->speak_started_at)->toBeNull()
        ->and($text->edit_speaker_id)->toBeNull()
        ->and($text->audio_count)->toBe(1)
        ->and($text->audio_male_count)->toBe(1)
        ->and($text->audio_female_count)->toBe(0);
});

it('replaces the previous audio file once the new upload succeeds', function () {
    Storage::fake('yandex-s3');
    Storage::disk('yandex-s3')->put('audio/old-file.mp3', 'old');

    $text = Text::factory()->create([
        'edit_audio_filename' => 'audio/old-file.mp3',
    ]);

    $this->postJson(
        route('admin.verify.speak.text.audio.complete', $text),
        ['audio' => UploadedFile::fake()->create('audio.mp3', 100, 'audio/mpeg')]
    )->assertSuccessful();

    Storage::disk('yandex-s3')->assertMissing('audio/old-file.mp3');
});

it('does not save audio or change state when the storage upload fails', function () {
    $text = Text::factory()->create([
        'speak_started_at' => now()->subMinutes(5),
        'edit_speaker_id' => $this->user->id,
        'edit_audio_filename' => 'audio/old-file.mp3',
        'audio_count' => 0,
    ]);

    Storage::shouldReceive('disk')->andReturnSelf();
    Storage::shouldReceive('putFileAs')->once()->andReturn(false);
    Storage::shouldReceive('delete')->never();

    $response = $this->postJson(
        route('admin.verify.speak.text.audio.complete', $text),
        ['audio' => UploadedFile::fake()->create('audio.mp3', 100, 'audio/mpeg')]
    );

    $response->assertStatus(503);

    expect($text->audio()->count())->toBe(0);

    $text->refresh();

    expect($text->speak_started_at)->not->toBeNull()
        ->and($text->edit_speaker_id)->toBe($this->user->id)
        ->and($text->edit_audio_filename)->toBe('audio/old-file.mp3');
});

it('requires a valid audio file', function () {
    $text = Text::factory()->create();

    $this->postJson(route('admin.verify.speak.text.audio.complete', $text), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('audio');
});
