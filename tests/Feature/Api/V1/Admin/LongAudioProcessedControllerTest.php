<?php

use App\Enums\AudioSplitStatusEnum;
use App\Enums\RoleEnum;
use App\Models\Audio;
use App\Models\File;
use App\Models\Text;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config('audio.disk'));
    Storage::fake(config('audio.legacy_disk'));

    $this->file = File::factory()->create();
    config(['dataset.main_file_id' => $this->file->id]);

    Sanctum::actingAs(User::factory()->create(['role' => RoleEnum::ADMIN->value]));
});

function splitParent(): Audio
{
    return Audio::factory()->for(Text::factory()->main(), 'text')->create([
        'edit_converted_audio_duration' => Audio::STT_MAX_DURATION_SAMPLES + 16000,
        'split_status' => AudioSplitStatusEnum::SPLIT,
    ]);
}

function addPart(Audio $parent, string $transcript, int $duration, string $filename): Audio
{
    $text = Text::factory()->create([
        'is_split_part' => true,
        'edit_original_transcript' => $transcript,
    ]);

    return $parent->splitParts()->create([
        'text_id' => $text->id,
        'edit_audio_filename' => $filename,
        'edit_converted_audio_duration' => $duration,
        'split_status' => AudioSplitStatusEnum::NONE,
    ]);
}

it('lists split and unsplittable audios but not pending or normal ones', function () {
    $split = splitParent();
    $unsplittable = Audio::factory()->for(Text::factory()->main(), 'text')->create([
        'edit_converted_audio_duration' => Audio::STT_MAX_DURATION_SAMPLES,
        'split_status' => AudioSplitStatusEnum::UNSPLITTABLE,
    ]);

    Audio::factory()->pendingSplit()->for(Text::factory()->main(), 'text')->create();
    Audio::factory()->for(Text::factory()->main(), 'text')->create();

    $ids = $this->getJson(route('admin.long.audio.processed'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$split->id, $unsplittable->id]);
});

it('returns the two split parts with their audio and transcripts', function () {
    $parent = splitParent();
    addPart($parent, 'first half', 200000, 'audio/part1.wav');
    addPart($parent, 'second half', 180000, 'audio/part2.wav');

    $this->getJson(route('admin.long.audio.processed', ['status' => 'split']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $parent->id)
        ->assertJsonPath('data.0.split_status', AudioSplitStatusEnum::SPLIT->value)
        ->assertJsonCount(2, 'data.0.parts')
        ->assertJsonPath('data.0.parts.0.audio_filename', 'part1.wav')
        ->assertJsonPath('data.0.parts.0.duration', 200000)
        ->assertJsonPath('data.0.parts.0.duration_seconds', 12.5)
        ->assertJsonPath('data.0.parts.0.original_transcript', 'first half')
        ->assertJsonPath('data.0.parts.1.original_transcript', 'second half')
        ->assertJsonPath('data.0.parts.0.split_status', AudioSplitStatusEnum::NONE->value);
});

it('returns an empty parts array for unsplittable audios', function () {
    Audio::factory()->for(Text::factory()->main(), 'text')->create([
        'edit_converted_audio_duration' => Audio::STT_MAX_DURATION_SAMPLES,
        'split_status' => AudioSplitStatusEnum::UNSPLITTABLE,
    ]);

    $this->getJson(route('admin.long.audio.processed', ['status' => 'unsplittable']))
        ->assertOk()
        ->assertJsonPath('data.0.parts', []);
});

it('filters by the status parameter', function () {
    $split = splitParent();
    $unsplittable = Audio::factory()->for(Text::factory()->main(), 'text')->create([
        'edit_converted_audio_duration' => Audio::STT_MAX_DURATION_SAMPLES,
        'split_status' => AudioSplitStatusEnum::UNSPLITTABLE,
    ]);

    expect($this->getJson(route('admin.long.audio.processed', ['status' => 'split']))->assertOk()->json('data.*.id'))
        ->toBe([$split->id]);

    expect($this->getJson(route('admin.long.audio.processed', ['status' => 'unsplittable']))->assertOk()->json('data.*.id'))
        ->toBe([$unsplittable->id]);
});

it('excludes processed audios whose text belongs to another file', function () {
    $main = splitParent();

    Audio::factory()->for(Text::factory(), 'text')->create([
        'edit_converted_audio_duration' => Audio::STT_MAX_DURATION_SAMPLES,
        'split_status' => AudioSplitStatusEnum::SPLIT,
    ]);

    $ids = $this->getJson(route('admin.long.audio.processed'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$main->id]);
});

it('respects the per_page parameter', function () {
    Audio::factory()->count(3)->for(Text::factory()->main(), 'text')->create([
        'edit_converted_audio_duration' => Audio::STT_MAX_DURATION_SAMPLES,
        'split_status' => AudioSplitStatusEnum::UNSPLITTABLE,
    ]);

    $response = $this->getJson(route('admin.long.audio.processed', ['per_page' => 2]))->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('meta.per_page'))->toBe(2);
});

it('does not leak a parts key into the pending split list', function () {
    Audio::factory()->pendingSplit()->for(Text::factory()->main(), 'text')->create();

    $this->getJson(route('admin.long.audio'))
        ->assertOk()
        ->assertJsonMissingPath('data.0.parts');
});
