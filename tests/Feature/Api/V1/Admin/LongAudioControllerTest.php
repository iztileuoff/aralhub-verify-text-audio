<?php

use App\Enums\AudioSplitStatusEnum;
use App\Enums\RoleEnum;
use App\Models\Audio;
use App\Models\Text;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('yandex-s3');

    Sanctum::actingAs(User::factory()->create(['role' => RoleEnum::ADMIN->value]));
});

it('lists only audios at or beyond the 30s sample threshold', function () {
    $atThreshold = Audio::factory()->create([
        'edit_converted_audio_duration' => Audio::STT_MAX_DURATION_SAMPLES,
    ]);
    $beyondThreshold = Audio::factory()->create([
        'edit_converted_audio_duration' => Audio::STT_MAX_DURATION_SAMPLES + 16000,
    ]);

    Audio::factory()->create([
        'edit_converted_audio_duration' => Audio::STT_MAX_DURATION_SAMPLES - 1,
    ]);
    Audio::factory()->create(['edit_converted_audio_duration' => null]);

    $ids = $this->getJson(route('admin.long.audio'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$atThreshold->id, $beyondThreshold->id]);
});

it('excludes audios already split or marked unsplittable', function () {
    $pending = Audio::factory()->create([
        'edit_converted_audio_duration' => Audio::STT_MAX_DURATION_SAMPLES,
        'split_status' => AudioSplitStatusEnum::PENDING,
    ]);

    Audio::factory()->create([
        'edit_converted_audio_duration' => Audio::STT_MAX_DURATION_SAMPLES,
        'split_status' => AudioSplitStatusEnum::SPLIT,
    ]);
    Audio::factory()->create([
        'edit_converted_audio_duration' => Audio::STT_MAX_DURATION_SAMPLES,
        'split_status' => AudioSplitStatusEnum::UNSPLITTABLE,
    ]);

    $ids = $this->getJson(route('admin.long.audio'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$pending->id]);
});

it('returns duration in samples and seconds plus the text transcript', function () {
    $text = Text::factory()->create([
        'edit_original_transcript' => 'orig',
        'edit_normalized_transcript' => 'norm',
        'edit_tokenized_transcript' => 'tok',
    ]);

    $audio = Audio::factory()->create([
        'text_id' => $text->id,
        'edit_converted_audio_duration' => 488000,
        'split_status' => AudioSplitStatusEnum::PENDING,
    ]);

    $this->getJson(route('admin.long.audio'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $audio->id)
        ->assertJsonPath('data.0.duration', 488000)
        ->assertJsonPath('data.0.duration_seconds', 30.5)
        ->assertJsonPath('data.0.split_status', AudioSplitStatusEnum::PENDING->value)
        ->assertJsonPath('data.0.original_transcript', 'orig')
        ->assertJsonPath('data.0.normalized_transcript', 'norm')
        ->assertJsonPath('data.0.tokenized_transcript', 'tok');
});

it('respects the per_page parameter', function () {
    Audio::factory()->count(3)->pendingSplit()->create();

    $response = $this->getJson(route('admin.long.audio', ['per_page' => 2]))->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('meta.per_page'))->toBe(2);
});
