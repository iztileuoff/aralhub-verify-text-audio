<?php

use App\Models\File;
use App\Models\Text;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

/** Put a candidate file on the public disk and run the check over it. */
function checkDuplicates(array $lines, array $options = []): int
{
    Storage::disk('public')->put('candidate.txt', implode("\n", $lines));

    return Artisan::call('import:check-duplicates', array_merge([
        'filename' => 'candidate.txt',
    ], $options));
}

it('keeps only lines that are neither repeated nor already stored', function () {
    Text::factory()->create([
        'edit_original_transcript' => 'Bar sóz.',
        'edit_normalized_transcript' => 'bar sóz',
    ]);

    checkDuplicates([
        'Bar sóz.',      // already in the database
        'Jańa sóz.',     // new
        'Jańa sóz.',     // repeated inside the file
        'Basqa sóz.',    // new
    ], ['--out' => 'clean.txt']);

    $clean = Storage::disk('public')->get('clean.txt');

    expect(trim($clean))->toBe("Jańa sóz.\nBasqa sóz.");
});

it('matches sentences that differ only in case or final punctuation', function () {
    Text::factory()->create([
        'edit_original_transcript' => 'Salem dúnya!',
        'edit_normalized_transcript' => 'salem dúnya',
    ]);

    checkDuplicates(['SALEM DÚNYA?', 'Basqa sóz.'], ['--out' => 'clean.txt']);

    expect(trim(Storage::disk('public')->get('clean.txt')))->toBe('Basqa sóz.');
});

it('falls back to the original transcript when the normalized column is empty', function () {
    // Rows imported before normalization existed keep NULL there.
    Text::factory()->create([
        'edit_original_transcript' => 'Eski sóz.',
        'edit_normalized_transcript' => null,
    ]);

    checkDuplicates(['Eski sóz.', 'Jańa sóz.'], ['--out' => 'clean.txt']);

    expect(trim(Storage::disk('public')->get('clean.txt')))->toBe('Jańa sóz.');
});

it('compares against one dataset only when --file-id is given', function () {
    $wanted = File::factory()->create();
    $other = File::factory()->create();

    Text::factory()->create([
        'file_id' => $other->id,
        'edit_original_transcript' => 'Basqa dataset.',
        'edit_normalized_transcript' => 'basqa dataset',
    ]);

    checkDuplicates(['Basqa dataset.'], ['--file-id' => $wanted->id, '--out' => 'clean.txt']);

    // The match lives in another dataset, so the line survives the filter.
    expect(trim(Storage::disk('public')->get('clean.txt')))->toBe('Basqa dataset.');
});

it('leaves the source file alone and writes nothing without --out', function () {
    checkDuplicates(['Birinshi.', 'Birinshi.']);

    expect(Storage::disk('public')->allFiles())->toBe(['candidate.txt'])
        ->and(trim(Storage::disk('public')->get('candidate.txt')))->toBe("Birinshi.\nBirinshi.");
});

it('refuses to overwrite the source file', function () {
    $exit = checkDuplicates(['Birinshi.'], ['--out' => 'candidate.txt']);

    expect($exit)->toBe(2)
        ->and(trim(Storage::disk('public')->get('candidate.txt')))->toBe('Birinshi.');
});

it('reports a missing file', function () {
    $exit = Artisan::call('import:check-duplicates', ['filename' => 'yoq.txt']);

    expect($exit)->toBe(2);
});
