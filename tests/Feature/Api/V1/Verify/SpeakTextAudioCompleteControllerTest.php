<?php

use App\Enums\GenderEnum;
use App\Enums\RoleEnum;
use App\Jobs\UploadSpeakAudioToYandex;
use App\Models\Text;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
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
    Storage::fake('local');
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
    Storage::disk('local')->assertMissing($audio->edit_audio_filename);

    $text->refresh();

    expect($text->speak_started_at)->toBeNull()
        ->and($text->edit_speaker_id)->toBeNull()
        ->and($text->audio_count)->toBe(1)
        ->and($text->audio_male_count)->toBe(1)
        ->and($text->audio_female_count)->toBe(0);
});

it('replaces the previous audio file once the new upload succeeds', function () {
    Storage::fake('local');
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

it('buffers the recording locally and defers the upload to a queued job', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::fake('yandex-s3');

    $text = Text::factory()->create([
        'speak_started_at' => now()->subMinutes(5),
        'edit_speaker_id' => $this->user->id,
        'edit_audio_filename' => 'audio/old-file.mp3',
    ]);

    $this->postJson(
        route('admin.verify.speak.text.audio.complete', $text),
        ['audio' => UploadedFile::fake()->create('audio.mp3', 100, 'audio/mpeg')]
    )->assertSuccessful();

    expect($text->audio()->count())->toBe(0)
        ->and(Storage::disk('yandex-s3')->allFiles())->toBeEmpty()
        ->and(Storage::disk('local')->files('audio'))->toHaveCount(1);

    Queue::assertPushed(UploadSpeakAudioToYandex::class, function (UploadSpeakAudioToYandex $job) use ($text) {
        return $job->textId === $text->id
            && $job->previousFilename === 'audio/old-file.mp3'
            && $job->speakerId === $this->user->id
            && $job->speakerGender === GenderEnum::MALE->value
            && str_starts_with($job->filename, 'audio/');
    });

    $text->refresh();

    expect($text->speak_started_at)->toBeNull()
        ->and($text->edit_speaker_id)->toBeNull();
});

it('uploads, persists the audio and refreshes counters when the job runs', function () {
    Storage::fake('local');
    Storage::fake('yandex-s3');
    Storage::disk('yandex-s3')->put('audio/old-file.mp3', 'old');
    Storage::disk('local')->put('audio/new-file.mp3', 'recording');

    $text = Text::factory()->create(['edit_audio_filename' => 'audio/old-file.mp3']);

    (new UploadSpeakAudioToYandex(
        textId: $text->id,
        filename: 'audio/new-file.mp3',
        previousFilename: 'audio/old-file.mp3',
        speakerId: $this->user->id,
        speakerGender: GenderEnum::MALE->value,
        speakStartedAt: now()->subMinutes(5)->toDateTimeString(),
        speakFinishedAt: now()->toDateTimeString(),
    ))->handle();

    Storage::disk('yandex-s3')->assertExists('audio/new-file.mp3');
    Storage::disk('yandex-s3')->assertMissing('audio/old-file.mp3');
    Storage::disk('local')->assertMissing('audio/new-file.mp3');

    $audio = $text->audio()->sole();

    expect($audio->edit_speaker_id)->toBe($this->user->id)
        ->and($audio->edit_speaker_gender)->toBe(GenderEnum::MALE);

    $text->refresh();

    expect($text->audio_count)->toBe(1)
        ->and($text->audio_male_count)->toBe(1)
        ->and($text->audio_female_count)->toBe(0);
});

it('discards the local buffer and persists no audio when the upload permanently fails', function () {
    Storage::fake('local');
    Storage::disk('local')->put('audio/new-file.mp3', 'recording');

    $text = Text::factory()->create();

    (new UploadSpeakAudioToYandex(
        textId: $text->id,
        filename: 'audio/new-file.mp3',
        previousFilename: null,
        speakerId: $this->user->id,
        speakerGender: GenderEnum::MALE->value,
        speakStartedAt: null,
        speakFinishedAt: now()->toDateTimeString(),
    ))->failed(new RuntimeException('upload failed'));

    Storage::disk('local')->assertMissing('audio/new-file.mp3');

    expect($text->audio()->count())->toBe(0);
});

it('requires a valid audio file', function () {
    $text = Text::factory()->create();

    $this->postJson(route('admin.verify.speak.text.audio.complete', $text), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('audio');
});
