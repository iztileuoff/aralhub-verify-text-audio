<?php

use App\Models\CallLandmark;
use App\Models\CallRecording;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function () {
    @unlink(storage_path('app/landmarks_test.csv'));
});

/**
 * A landmark tied to the given number of calls.
 */
function landmarkWithCalls(string $name, int $calls): CallLandmark
{
    $landmark = CallLandmark::factory()->create([
        'name' => $name,
        'normalized_name' => CallLandmark::normalize($name),
    ]);

    CallRecording::factory()->count($calls)->create(['landmark_id' => $landmark->id]);

    return $landmark;
}

/**
 * @return array<int, array<int, string>>
 */
function readLandmarksCsv(): array
{
    $handle = fopen(storage_path('app/landmarks_test.csv'), 'r');
    $rows = [];

    while (($row = fgetcsv($handle, escape: '')) !== false) {
        $rows[] = $row;
    }

    fclose($handle);

    return $rows;
}

it('writes every landmark with its call count, most mentioned first', function () {
    landmarkWithCalls('Yurfak', 2);
    landmarkWithCalls('Mateke', 5);
    landmarkWithCalls('Старый базар', 1);

    $this->artisan('calls:export-landmarks', ['--filename' => 'landmarks_test.csv'])
        ->expectsOutputToContain('Exported 3 landmarks (8 mentions)')
        ->assertSuccessful();

    expect(readLandmarksCsv())->toBe([
        ['text', 'mentions'],
        ['Mateke', '5'],
        ['Yurfak', '2'],
        ['Старый базар', '1'],
    ]);
});

it('quotes names with commas and quotes the CSV way', function () {
    landmarkWithCalls('ТЦ "Нукус", 2 этаж', 1);

    $this->artisan('calls:export-landmarks', ['--filename' => 'landmarks_test.csv'])->assertSuccessful();

    expect(file_get_contents(storage_path('app/landmarks_test.csv')))
        ->toBe("text,mentions\n\"ТЦ \"\"Нукус\"\", 2 этаж\",1\n")
        ->and(readLandmarksCsv()[1])->toBe(['ТЦ "Нукус", 2 этаж', '1']);
});

it('counts a landmark whose calls are gone as one mention', function () {
    CallLandmark::factory()->create(['name' => 'Flag', 'normalized_name' => 'flag']);

    $this->artisan('calls:export-landmarks', ['--filename' => 'landmarks_test.csv'])->assertSuccessful();

    expect(readLandmarksCsv()[1])->toBe(['Flag', '1']);
});

it('leaves out names that look like phone numbers and lists them', function () {
    landmarkWithCalls('Mateke', 1);
    landmarkWithCalls('Ali +998 90 123-45-67', 1);

    $this->artisan('calls:export-landmarks', ['--filename' => 'landmarks_test.csv'])
        ->expectsOutputToContain('Exported 1 landmarks')
        ->expectsOutputToContain('Ali +998 90 123-45-67')
        ->assertSuccessful();

    expect(readLandmarksCsv())->toBe([['text', 'mentions'], ['Mateke', '1']]);
});

it('exports only landmarks newer than --after-id and names the last id', function () {
    $old = landmarkWithCalls('Mateke', 3);
    $new = landmarkWithCalls('Yurfak', 1);

    $this->artisan('calls:export-landmarks', ['--filename' => 'landmarks_test.csv', '--after-id' => $old->id])
        ->expectsOutputToContain('Exported 1 landmarks')
        ->expectsOutputToContain("--after-id={$new->id}")
        ->assertSuccessful();

    expect(readLandmarksCsv())->toBe([['text', 'mentions'], ['Yurfak', '1']]);
});
