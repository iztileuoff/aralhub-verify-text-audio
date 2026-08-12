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

    Sanctum::actingAs(User::factory()->create(['role' => RoleEnum::ADMIN->value]));

    $this->speaker = User::factory()->create(['role' => RoleEnum::SPEAKER->value]);
});

it('counts only recordings of the configured dataset file', function () {
    $mainText = Text::factory()->main()->create();
    $otherText = Text::factory()->create();

    Audio::factory()->create([
        'text_id' => $mainText->id,
        'edit_speaker_id' => $this->speaker->id,
        'speak_finished_at' => '2026-08-10 10:00:00',
        'is_correct' => true,
    ]);

    Audio::factory()->create([
        'text_id' => $mainText->id,
        'edit_speaker_id' => $this->speaker->id,
        'speak_finished_at' => '2026-08-11 10:00:00',
        'is_correct' => false,
    ]);

    // Recording on another dataset file: must not reach any counter.
    Audio::factory()->create([
        'text_id' => $otherText->id,
        'edit_speaker_id' => $this->speaker->id,
        'speak_finished_at' => '2026-08-10 10:00:00',
        'is_correct' => true,
    ]);

    $data = $this->getJson(route('admin.report.speakers', [
        'from_date' => '2026-08-10',
        'to_date' => '2026-08-11',
    ]))->assertOk()->json('data.speakers');

    expect($data)->toHaveCount(1)
        ->and($data[0]['id'])->toBe($this->speaker->id)
        ->and($data[0]['period']['written_count'])->toBe(2)
        ->and($data[0]['total'])->toMatchArray([
            'written_count' => 2,
            'checked_count' => 2,
            'correct_count' => 1,
            'incorrect_count' => 1,
        ]);
});

it('drops speakers whose recordings all belong to another dataset file', function () {
    $otherText = Text::factory()->create();

    Audio::factory()->create([
        'text_id' => $otherText->id,
        'edit_speaker_id' => $this->speaker->id,
        'speak_finished_at' => '2026-08-10 10:00:00',
    ]);

    $data = $this->getJson(route('admin.report.speakers', [
        'from_date' => '2026-08-10',
        'to_date' => '2026-08-10',
    ]))->assertOk()->json('data.speakers');

    expect($data)->toBeEmpty();
});

it('follows the dataset file id from config, not a hard-coded value', function () {
    $newDataset = File::factory()->create();

    $oldText = Text::factory()->main()->create();
    $newText = Text::factory()->create(['file_id' => $newDataset->id, 'is_main' => true]);

    Audio::factory()->create([
        'text_id' => $oldText->id,
        'edit_speaker_id' => $this->speaker->id,
        'speak_finished_at' => '2026-08-10 10:00:00',
    ]);

    Audio::factory()->count(3)->create([
        'text_id' => $newText->id,
        'edit_speaker_id' => $this->speaker->id,
        'speak_finished_at' => '2026-08-10 10:00:00',
    ]);

    config(['dataset.main_file_id' => $newDataset->id]);

    $data = $this->getJson(route('admin.report.speakers', [
        'from_date' => '2026-08-10',
        'to_date' => '2026-08-10',
    ]))->assertOk()->json('data.speakers');

    expect($data)->toHaveCount(1)
        ->and($data[0]['total']['written_count'])->toBe(3);
});
