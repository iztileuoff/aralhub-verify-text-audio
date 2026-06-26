<?php

use App\Enums\GenderEnum;
use App\Enums\RoleEnum;
use App\Models\Audio;
use App\Models\File;
use App\Models\Text;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->file = File::factory()->create();
    config(['dataset.main_file_id' => $this->file->id]);

    $this->speaker = User::factory()->create([
        'role' => RoleEnum::SPEAKER->value,
        'gender' => GenderEnum::MALE->value,
    ]);

    Sanctum::actingAs($this->speaker);
});

it('resumes the text the speaker has already started over picking a new one', function () {
    $held = Text::factory()->main()->create([
        'speak_started_at' => now()->subMinutes(10),
        'edit_speaker_id' => $this->speaker->id,
    ]);

    Text::factory()->main()->create();

    $id = $this->getJson(route('admin.verify.speak.text'))
        ->assertOk()
        ->json('data.id');

    expect($id)->toBe($held->id);
});

it('prefers a never-recorded text (null audio count) over partially recorded ones', function () {
    Text::factory()->main()->create(['audio_count' => 4]);
    $target = Text::factory()->main()->create(['audio_count' => null]);

    $id = $this->getJson(route('admin.verify.speak.text'))->assertOk()->json('data.id');

    expect($id)->toBe($target->id);
});

it('picks the text with the lowest audio count when none are untouched', function () {
    Text::factory()->main()->create(['audio_count' => 5]);
    $target = Text::factory()->main()->create(['audio_count' => 2]);
    Text::factory()->main()->create(['audio_count' => 8]);

    $id = $this->getJson(route('admin.verify.speak.text'))->assertOk()->json('data.id');

    expect($id)->toBe($target->id);
});

it('selects one of the eligible texts when several share the top tier', function () {
    $eligibleIds = Text::factory()->main()->count(5)->create()->pluck('id');

    $id = $this->getJson(route('admin.verify.speak.text'))->assertOk()->json('data.id');

    expect($eligibleIds)->toContain($id);
});

it('only selects eligible main texts', function () {
    $target = Text::factory()->main()->create();
    $otherSpeaker = User::factory()->create(['role' => RoleEnum::SPEAKER->value]);

    Text::factory()->create();
    Text::factory()->create(['file_id' => $this->file->id, 'is_main' => false]);
    Text::factory()->main()->create(['has_text_error' => true]);
    Text::factory()->main()->create(['edit_original_transcript' => null]);
    Text::factory()->main()->create([
        'speak_started_at' => now(),
        'edit_speaker_id' => $otherSpeaker->id,
    ]);
    Text::factory()->main()->create(['audio_count' => 10]);

    $id = $this->getJson(route('admin.verify.speak.text'))->assertOk()->json('data.id');

    expect($id)->toBe($target->id);
});

it('skips texts the speaker has already recorded an audio for', function () {
    $recorded = Text::factory()->main()->create();
    Audio::factory()->create([
        'text_id' => $recorded->id,
        'edit_speaker_id' => $this->speaker->id,
    ]);

    $target = Text::factory()->main()->create();

    $id = $this->getJson(route('admin.verify.speak.text'))->assertOk()->json('data.id');

    expect($id)->toBe($target->id);
});

it('returns 404 when there are no texts to record', function () {
    Text::factory()->main()->create(['audio_count' => 10]);

    $this->getJson(route('admin.verify.speak.text'))
        ->assertNotFound()
        ->assertJson(['message' => 'Тексты для аудиозаписи отсутствуют.']);
});

it('locks the selected text to the current speaker', function () {
    $target = Text::factory()->main()->create();

    $this->getJson(route('admin.verify.speak.text'))->assertOk();

    $target->refresh();

    expect($target->speak_started_at)->not->toBeNull()
        ->and($target->edit_speaker_id)->toBe($this->speaker->id);
});
