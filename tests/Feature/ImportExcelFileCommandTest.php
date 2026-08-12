<?php

use App\Models\File;
use App\Models\Text;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $this->user = User::factory()->create();
});

/**
 * Put a spreadsheet on the public disk and import it. FastExcel reads CSV
 * through the same path as XLSX, so the header handling is exercised as is.
 *
 * @param  array<int, array<string, string>>  $rows
 */
function importSheet(array $rows, array $options = [], string $header = "id,text\n"): int
{
    $body = '';

    foreach ($rows as $row) {
        $body .= implode(',', array_map(fn ($v) => '"'.$v.'"', $row))."\n";
    }

    Storage::disk('public')->put('batch.csv', $header.$body);

    return Artisan::call('import:excel-file', array_merge([
        'filename' => 'batch.csv',
        '--user' => test()->user->id,
    ], $options));
}

it('imports the rows and completes the created file', function () {
    $exit = importSheet([
        ['kaa-000001', 'Allo tıńlap turman'],
        ['kaa-000002', 'Bul kim'],
    ]);

    $file = File::sole();

    expect($exit)->toBe(0)
        ->and($file->status)->toBe(File::STATUS_COMPLETED)
        ->and($file->rows_imported)->toBe(2)
        ->and(Text::count())->toBe(2)
        ->and(Text::pluck('edit_original_transcript')->all())
        ->toBe(['Allo tıńlap turman', 'Bul kim']);
});

it('derives a numeric transcript_id from a prefixed source id', function () {
    importSheet([['kaa-000042', 'Jaqsı']]);

    expect(Text::sole()->transcript_id)->toBe(42);
});

it('leaves transcript_id null when the source id has no digits', function () {
    importSheet([['no-digits-here', 'Jaqsı']]);

    expect(Text::sole()->transcript_id)->toBeNull();
});

it('reads the id column whatever its capitalisation', function () {
    importSheet([['kaa-000007', 'Jaqsı']], [], "ID,TEXT\n");

    expect(Text::sole()->transcript_id)->toBe(7);
});

it('skips rows whose text is empty', function () {
    importSheet([
        ['kaa-000001', 'Birinshi'],
        ['kaa-000002', ''],
        ['kaa-000003', '   '],
        ['kaa-000004', 'Ekinshi'],
    ]);

    expect(Text::count())->toBe(2)
        ->and(File::sole()->rows_imported)->toBe(2)
        ->and(Text::pluck('edit_original_transcript')->all())->toBe(['Birinshi', 'Ekinshi']);
});

it('fills the same derived columns as the plain-text import', function () {
    importSheet([['kaa-000001', 'Sálem, dúnya!']]);

    $text = Text::sole();

    expect($text->edit_normalized_transcript)->toBe('sálem, dúnya')
        ->and($text->edit_tokenized_transcript)->toBe('s á l e m , | d ú n y a |')
        ->and($text->is_main)->toBeTrue()
        ->and($text->edit_user_id)->toBe($this->user->id)
        ->and($text->file_id)->toBe(File::sole()->id);
});

it('appends to an existing dataset instead of creating another file', function () {
    importSheet([['kaa-000001', 'Birinshi']]);

    $file = File::sole();

    $exit = importSheet([['kaa-000002', 'Ekinshi']], ['--file-id' => $file->id]);

    expect($exit)->toBe(0)
        ->and(File::count())->toBe(1)
        ->and(Text::count())->toBe(2)
        ->and(Text::pluck('file_id')->unique()->all())->toBe([$file->id])
        ->and($file->refresh()->rows_imported)->toBe(2)
        ->and($file->rows_total)->toBe(2);
});

it('does not import the same batch twice when appending', function () {
    importSheet([['kaa-000001', 'Birinshi'], ['kaa-000002', 'Ekinshi']]);

    $file = File::sole();

    importSheet([
        ['kaa-000001', 'Birinshi.'],
        ['kaa-000003', 'Úshinshi'],
    ], ['--file-id' => $file->id]);

    expect(Text::count())->toBe(3)
        ->and($file->refresh()->rows_imported)->toBe(3);
});

it('drops rows repeated inside one batch', function () {
    importSheet([
        ['kaa-000001', 'Birinshi'],
        ['kaa-000002', 'Birinshi'],
    ]);

    expect(Text::count())->toBe(1);
});

it('labels the dataset it creates', function () {
    importSheet([['kaa-000001', 'Birinshi']], ['--label' => 'v3']);

    expect(File::sole()->label)->toBe('v3');
});

it('renames the dataset when a label comes with an append', function () {
    importSheet([['kaa-000001', 'Birinshi']], ['--label' => 'v3']);

    $file = File::sole();

    importSheet([['kaa-000002', 'Ekinshi']], ['--file-id' => $file->id]);
    expect($file->refresh()->label)->toBe('v3');

    importSheet([['kaa-000003', 'Úshinshi']], ['--file-id' => $file->id, '--label' => 'v3.1']);
    expect($file->refresh()->label)->toBe('v3.1');
});

it('refuses a file whose header row is missing instead of importing nothing', function () {
    // The customer's batches are cut out of a master sheet, and the cut drops
    // the header: the first data row would be eaten as one.
    $exit = importSheet(
        [['kaa-002500', 'Ekinshi']],
        [],
        "kaa-002499,Birinshi\n",
    );

    expect($exit)->toBe(2)
        ->and(File::count())->toBe(0)
        ->and(Text::count())->toBe(0);
});

it('refuses to append to a file that does not exist', function () {
    $exit = importSheet([['kaa-000001', 'Birinshi']], ['--file-id' => 999]);

    expect($exit)->toBe(2)
        ->and(Text::count())->toBe(0)
        ->and(File::count())->toBe(0);
});
