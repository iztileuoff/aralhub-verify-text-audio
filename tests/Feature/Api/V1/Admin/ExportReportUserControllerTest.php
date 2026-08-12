<?php

use App\Enums\RoleEnum;
use App\Models\Audio;
use App\Models\File;
use App\Models\Text;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;
use Rap2hpoutre\FastExcel\FastExcel;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->file = File::factory()->create();
    config(['dataset.main_file_id' => $this->file->id]);

    Sanctum::actingAs(User::factory()->create(['role' => RoleEnum::ADMIN->value]));

    $this->speaker = User::factory()->create(['role' => RoleEnum::SPEAKER->value]);
});

afterEach(function () {
    @unlink(storage_path('app/test_users_report.xlsx'));
});

/**
 * Request the report for a single day and read the downloaded sheet back.
 */
function downloadUsersReport(string $day = '2026-08-10'): Collection
{
    $content = test()->postJson(route('admin.export.report-users'), [
        'from_date' => $day,
        'to_date' => $day,
    ])->assertOk()->streamedContent();

    $path = storage_path('app/test_users_report.xlsx');
    file_put_contents($path, $content);

    return collect((new FastExcel)->import($path));
}

it('counts only recordings of the configured dataset file', function () {
    $mainText = Text::factory()->main()->create();
    $otherText = Text::factory()->create();

    Audio::factory()->count(2)->create([
        'text_id' => $mainText->id,
        'edit_speaker_id' => $this->speaker->id,
        'speak_finished_at' => '2026-08-10 09:00:00',
    ]);

    // Recordings on another dataset file: must not reach the sheet.
    Audio::factory()->count(5)->create([
        'text_id' => $otherText->id,
        'edit_speaker_id' => $this->speaker->id,
        'speak_finished_at' => '2026-08-10 09:00:00',
    ]);

    $rows = downloadUsersReport();

    // One speaker row plus the trailing TOTAL summary row.
    expect($rows)->toHaveCount(2)
        ->and($rows->first()['ID'])->toBe($this->speaker->id)
        ->and($rows->first()['2026-08-10'])->toBe(2)
        ->and($rows->first()['TOTAL'])->toBe(2)
        ->and($rows->last()['Full Name'])->toBe('TOTAL')
        ->and($rows->last()['TOTAL'])->toBe(2);
});

it('follows the dataset file id from config, not a hard-coded value', function () {
    $newDataset = File::factory()->create();
    $newText = Text::factory()->create(['file_id' => $newDataset->id, 'is_main' => true]);

    Audio::factory()->count(3)->create([
        'text_id' => $newText->id,
        'edit_speaker_id' => $this->speaker->id,
        'speak_finished_at' => '2026-08-10 09:00:00',
    ]);

    config(['dataset.main_file_id' => $newDataset->id]);

    $rows = downloadUsersReport();

    expect($rows)->toHaveCount(2)
        ->and($rows->first()['TOTAL'])->toBe(3);
});
