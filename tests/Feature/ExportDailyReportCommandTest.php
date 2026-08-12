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
    @unlink(storage_path('app/test_daily.xlsx'));
});

/**
 * Run the command for a single day and return its two sheets as [speakers, moderators].
 *
 * @return array{0: Collection, 1: Collection}
 */
function runDailyReport(string $day = '2026-08-10'): array
{
    Artisan::call('report:daily', [
        '--filename' => 'test_daily.xlsx',
        '--from' => $day,
        '--to' => $day,
    ]);

    $sheets = (new FastExcel)->importSheets(storage_path('app/test_daily.xlsx'));

    return [collect($sheets->first()), collect($sheets->last())];
}

it('counts only the day of the configured dataset file', function () {
    $speaker = User::factory()->create(['role' => RoleEnum::SPEAKER->value]);
    $moderator = User::factory()->create(['role' => RoleEnum::MODERATOR->value]);

    $mainText = Text::factory()->main()->create();
    $otherText = Text::factory()->create();

    Audio::factory()->count(2)->create([
        'text_id' => $mainText->id,
        'edit_speaker_id' => $speaker->id,
        'speak_finished_at' => '2026-08-10 09:00:00',
        'moderator_id' => $moderator->id,
        'moderator_finished_at' => '2026-08-10 12:00:00',
    ]);

    // Work on another dataset file: must stay out of both sheets.
    Audio::factory()->count(4)->create([
        'text_id' => $otherText->id,
        'edit_speaker_id' => $speaker->id,
        'speak_finished_at' => '2026-08-10 09:00:00',
        'moderator_id' => $moderator->id,
        'moderator_finished_at' => '2026-08-10 12:00:00',
    ]);

    [$speakers, $moderators] = runDailyReport();

    expect($speakers)->toHaveCount(1)
        ->and($speakers->first()['2026-08-10'])->toBe(2)
        ->and($speakers->first()['Total'])->toBe(2)
        ->and($moderators)->toHaveCount(1)
        ->and($moderators->first()['2026-08-10'])->toBe(2)
        ->and($moderators->first()['Total'])->toBe(2);
});

it('follows the dataset file id from config, not a hard-coded value', function () {
    $speaker = User::factory()->create(['role' => RoleEnum::SPEAKER->value]);

    $newDataset = File::factory()->create();
    $newText = Text::factory()->create(['file_id' => $newDataset->id, 'is_main' => true]);

    Audio::factory()->count(3)->create([
        'text_id' => $newText->id,
        'edit_speaker_id' => $speaker->id,
        'speak_finished_at' => '2026-08-10 09:00:00',
    ]);

    [$speakers] = runDailyReport();

    expect($speakers)->toBeEmpty();

    config(['dataset.main_file_id' => $newDataset->id]);

    [$speakers] = runDailyReport();

    expect($speakers)->toHaveCount(1)
        ->and($speakers->first()['Total'])->toBe(3);
});
