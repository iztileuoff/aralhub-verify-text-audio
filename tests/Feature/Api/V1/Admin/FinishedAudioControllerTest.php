<?php

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
    Storage::fake('yandex-s3');

    $this->file = File::factory()->create();
    config(['dataset.main_file_id' => $this->file->id]);

    Sanctum::actingAs(User::factory()->create(['role' => RoleEnum::ADMIN->value]));
});

it('lists only accepted audios of the active dataset file', function () {
    $mainText = Text::factory()->main()->create();
    $otherText = Text::factory()->create();

    $accepted = Audio::factory()->create([
        'text_id' => $mainText->id,
        'is_correct' => true,
        'edit_converted_audio_duration' => 100,
    ]);

    // Rejected on the active file, and accepted on a previous dataset: both excluded.
    Audio::factory()->create([
        'text_id' => $mainText->id,
        'is_correct' => false,
        'edit_converted_audio_duration' => 100,
    ]);

    Audio::factory()->count(3)->create([
        'text_id' => $otherText->id,
        'is_correct' => true,
        'edit_converted_audio_duration' => 100,
    ]);

    $ids = collect($this->getJson(route('admin.finished.audio'))->assertOk()->json('data'))
        ->pluck('id');

    expect($ids->all())->toBe([$accepted->id]);
});

it('still excludes audios that exceed the STT length limit', function () {
    $mainText = Text::factory()->main()->create();

    Audio::factory()->create([
        'text_id' => $mainText->id,
        'is_correct' => true,
        'edit_converted_audio_duration' => Audio::STT_MAX_DURATION_SAMPLES,
    ]);

    $short = Audio::factory()->create([
        'text_id' => $mainText->id,
        'is_correct' => true,
        'edit_converted_audio_duration' => Audio::STT_MAX_DURATION_SAMPLES - 1,
    ]);

    $ids = collect($this->getJson(route('admin.finished.audio'))->assertOk()->json('data'))
        ->pluck('id');

    expect($ids->all())->toBe([$short->id]);
});
