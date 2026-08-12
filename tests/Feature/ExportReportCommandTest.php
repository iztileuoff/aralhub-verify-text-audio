<?php

use App\Enums\RoleEnum;
use App\Models\Audio;
use App\Models\File;
use App\Models\Text;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Rap2hpoutre\FastExcel\FastExcel;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->file = File::factory()->create();
    config(['dataset.main_file_id' => $this->file->id]);
});

afterEach(function () {
    @unlink(storage_path('app/test_report.xlsx'));
});

/**
 * Run the command and return its two sheets as [speakers, moderators].
 *
 * @return array{0: Collection, 1: Collection}
 */
function runReportExport(array $options = []): array
{
    Artisan::call('report:export', $options + ['--filename' => 'test_report.xlsx']);

    $sheets = (new FastExcel)->importSheets(storage_path('app/test_report.xlsx'));

    return [collect($sheets->first()), collect($sheets->last())];
}

it('counts only recordings and checks of the configured dataset file', function () {
    $speaker = User::factory()->create(['role' => RoleEnum::SPEAKER->value]);
    $moderator = User::factory()->create(['role' => RoleEnum::MODERATOR->value]);

    $mainText = Text::factory()->main()->create();
    $otherText = Text::factory()->create();

    Audio::factory()->count(2)->create([
        'text_id' => $mainText->id,
        'edit_speaker_id' => $speaker->id,
        'speak_finished_at' => now(),
        'moderator_id' => $moderator->id,
        'moderator_finished_at' => now(),
        'is_correct' => true,
    ]);

    // Work on another dataset file: must stay out of both sheets.
    Audio::factory()->count(5)->create([
        'text_id' => $otherText->id,
        'edit_speaker_id' => $speaker->id,
        'speak_finished_at' => now(),
        'moderator_id' => $moderator->id,
        'moderator_finished_at' => now(),
        'is_correct' => true,
    ]);

    [$speakers, $moderators] = runReportExport();

    expect($speakers)->toHaveCount(1)
        ->and($speakers->first()['Written'])->toBe(2)
        ->and($speakers->first()['Correct'])->toBe(2)
        ->and($moderators)->toHaveCount(1)
        ->and($moderators->first()['Checked'])->toBe(2);
});

it('follows the dataset file id from config, not a hard-coded value', function () {
    $speaker = User::factory()->create(['role' => RoleEnum::SPEAKER->value]);

    $newDataset = File::factory()->create();
    $newText = Text::factory()->create(['file_id' => $newDataset->id, 'is_main' => true]);

    Audio::factory()->count(3)->create([
        'text_id' => $newText->id,
        'edit_speaker_id' => $speaker->id,
        'speak_finished_at' => now(),
        'is_correct' => true,
    ]);

    [$speakers] = runReportExport();

    expect($speakers)->toBeEmpty();

    config(['dataset.main_file_id' => $newDataset->id]);

    [$speakers] = runReportExport();

    expect($speakers)->toHaveCount(1)
        ->and($speakers->first()['Written'])->toBe(3);
});
