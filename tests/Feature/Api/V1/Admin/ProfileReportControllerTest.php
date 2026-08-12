<?php

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

    $this->user = User::factory()->create(['role' => RoleEnum::SPEAKER->value]);
    Sanctum::actingAs($this->user);
});

it('reports only the own recordings made on the active dataset file', function () {
    $mainText = Text::factory()->main()->create();
    $otherText = Text::factory()->create();

    Audio::factory()->create([
        'text_id' => $mainText->id,
        'edit_speaker_id' => $this->user->id,
        'speak_finished_at' => '2026-08-10 10:00:00',
        'is_correct' => true,
    ]);

    Audio::factory()->create([
        'text_id' => $mainText->id,
        'edit_speaker_id' => $this->user->id,
        'speak_finished_at' => '2026-08-10 11:00:00',
        'is_correct' => false,
    ]);

    // Recordings on another dataset file: excluded.
    Audio::factory()->count(4)->create([
        'text_id' => $otherText->id,
        'edit_speaker_id' => $this->user->id,
        'speak_finished_at' => '2026-08-10 10:00:00',
        'is_correct' => true,
    ]);

    $audio = $this->getJson(route('admin.profile.report', [
        'from_date' => '2026-08-10',
        'to_date' => '2026-08-10',
    ]))->assertOk()->json('data.audio');

    expect($audio)->toMatchArray([
        'written_count' => 2,
        'checked_count' => 2,
        'correct_count' => 1,
        'incorrect_count' => 1,
    ])
        ->and($audio['by_date'][0]['written_count'])->toBe(2);
});

it('reports only the own moderation done on the active dataset file', function () {
    $mainText = Text::factory()->main()->create();
    $otherText = Text::factory()->create();

    Audio::factory()->create([
        'text_id' => $mainText->id,
        'moderator_id' => $this->user->id,
        'moderator_finished_at' => '2026-08-10 10:00:00',
        'is_correct' => true,
    ]);

    // Checks on another dataset file: excluded.
    Audio::factory()->count(3)->create([
        'text_id' => $otherText->id,
        'moderator_id' => $this->user->id,
        'moderator_finished_at' => '2026-08-10 10:00:00',
        'is_correct' => true,
    ]);

    $moderation = $this->getJson(route('admin.profile.report', [
        'from_date' => '2026-08-10',
        'to_date' => '2026-08-10',
    ]))->assertOk()->json('data.moderation');

    expect($moderation)->toMatchArray([
        'checked_count' => 1,
        'correct_count' => 1,
        'incorrect_count' => 0,
    ]);
});

it('follows the dataset file id from config, not the file the work was done on', function () {
    $newDataset = File::factory()->create();

    $oldText = Text::factory()->main()->create();
    $newText = Text::factory()->create(['file_id' => $newDataset->id, 'is_main' => true]);

    Audio::factory()->create([
        'text_id' => $oldText->id,
        'edit_speaker_id' => $this->user->id,
        'speak_finished_at' => '2026-08-10 10:00:00',
    ]);

    Audio::factory()->count(2)->create([
        'text_id' => $newText->id,
        'edit_speaker_id' => $this->user->id,
        'speak_finished_at' => '2026-08-10 10:00:00',
    ]);

    config(['dataset.main_file_id' => $newDataset->id]);

    $audio = $this->getJson(route('admin.profile.report', [
        'from_date' => '2026-08-10',
        'to_date' => '2026-08-10',
    ]))->assertOk()->json('data.audio');

    expect($audio['written_count'])->toBe(2);
});
