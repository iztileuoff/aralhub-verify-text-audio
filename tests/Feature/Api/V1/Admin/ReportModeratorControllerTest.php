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

    $this->moderator = User::factory()->create(['role' => RoleEnum::MODERATOR->value]);
});

it('counts only checks of the configured dataset file', function () {
    $mainText = Text::factory()->main()->create();
    $otherText = Text::factory()->create();

    Audio::factory()->create([
        'text_id' => $mainText->id,
        'moderator_id' => $this->moderator->id,
        'moderator_finished_at' => '2026-08-10 10:00:00',
        'is_correct' => true,
    ]);

    Audio::factory()->create([
        'text_id' => $mainText->id,
        'moderator_id' => $this->moderator->id,
        'moderator_finished_at' => '2026-08-11 10:00:00',
        'is_correct' => false,
    ]);

    // Check on another dataset file: must not reach any counter.
    Audio::factory()->create([
        'text_id' => $otherText->id,
        'moderator_id' => $this->moderator->id,
        'moderator_finished_at' => '2026-08-10 10:00:00',
        'is_correct' => true,
    ]);

    $data = $this->getJson(route('admin.report.moderators', [
        'from_date' => '2026-08-10',
        'to_date' => '2026-08-11',
    ]))->assertOk()->json('data.moderators');

    expect($data)->toHaveCount(1)
        ->and($data[0]['id'])->toBe($this->moderator->id)
        ->and($data[0]['period'])->toMatchArray([
            'checked_count' => 2,
            'correct_count' => 1,
            'incorrect_count' => 1,
        ])
        ->and($data[0]['total'])->toMatchArray([
            'checked_count' => 2,
            'correct_count' => 1,
            'incorrect_count' => 1,
        ]);
});

it('drops moderators whose checks all belong to another dataset file', function () {
    $otherText = Text::factory()->create();

    Audio::factory()->create([
        'text_id' => $otherText->id,
        'moderator_id' => $this->moderator->id,
        'moderator_finished_at' => '2026-08-10 10:00:00',
        'is_correct' => true,
    ]);

    $data = $this->getJson(route('admin.report.moderators', [
        'from_date' => '2026-08-10',
        'to_date' => '2026-08-10',
    ]))->assertOk()->json('data.moderators');

    expect($data)->toBeEmpty();
});

it('follows the dataset file id from config, not a hard-coded value', function () {
    $newDataset = File::factory()->create();

    $oldText = Text::factory()->main()->create();
    $newText = Text::factory()->create(['file_id' => $newDataset->id, 'is_main' => true]);

    Audio::factory()->create([
        'text_id' => $oldText->id,
        'moderator_id' => $this->moderator->id,
        'moderator_finished_at' => '2026-08-10 10:00:00',
        'is_correct' => true,
    ]);

    Audio::factory()->count(3)->create([
        'text_id' => $newText->id,
        'moderator_id' => $this->moderator->id,
        'moderator_finished_at' => '2026-08-10 10:00:00',
        'is_correct' => true,
    ]);

    config(['dataset.main_file_id' => $newDataset->id]);

    $data = $this->getJson(route('admin.report.moderators', [
        'from_date' => '2026-08-10',
        'to_date' => '2026-08-10',
    ]))->assertOk()->json('data.moderators');

    expect($data)->toHaveCount(1)
        ->and($data[0]['total']['checked_count'])->toBe(3);
});
