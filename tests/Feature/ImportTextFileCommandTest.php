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

/** Put a source file on the public disk and import it. */
function importLines(array $lines, array $options = []): int
{
    Storage::disk('public')->put('dataset.txt', implode("\n", $lines));

    return Artisan::call('import:text-file', array_merge([
        'filename' => 'dataset.txt',
        '--user' => test()->user->id,
    ], $options));
}

it('imports every line and marks the file completed', function () {
    $exit = importLines(['Birinshi qatar.', 'Ekinshi qatar.', 'Úshinshi qatar.']);

    $file = File::sole();

    expect($exit)->toBe(0)
        ->and($file->status)->toBe(File::STATUS_COMPLETED)
        ->and($file->rows_total)->toBe(3)
        ->and($file->rows_imported)->toBe(3)
        ->and(Text::count())->toBe(3);
});

it('writes the same columns the per-row create used to write', function () {
    importLines(['Sálem, dúnya!']);

    $text = Text::sole();
    $file = File::sole();

    expect($text->file_id)->toBe($file->id)
        ->and($text->edit_original_transcript)->toBe('Sálem, dúnya!')
        ->and($text->edit_normalized_transcript)->toBe('sálem, dúnya')
        ->and($text->edit_tokenized_transcript)->toBe('s á l e m , | d ú n y a |')
        ->and($text->edit_user_id)->toBe($this->user->id)
        ->and($text->is_main)->toBeTrue()
        ->and($text->is_split_part)->toBeFalse()
        ->and($text->has_text_error)->toBeFalse()
        ->and($text->edit_started_at)->not->toBeNull()
        ->and($text->edit_finished_at)->not->toBeNull()
        ->and($text->created_at)->not->toBeNull()
        ->and($text->updated_at)->not->toBeNull();
});

it('imports across chunk boundaries without losing or duplicating rows', function () {
    $lines = array_map(fn (int $i): string => "Qatar nomeri {$i}.", range(1, 25));

    importLines($lines, ['--chunk' => 10]);

    $transcripts = Text::query()->pluck('edit_original_transcript');

    expect(Text::count())->toBe(25)
        ->and(File::sole()->rows_imported)->toBe(25)
        ->and($transcripts->unique())->toHaveCount(25)
        ->and($transcripts->first())->toBe('Qatar nomeri 1.')
        ->and($transcripts->last())->toBe('Qatar nomeri 25.');
});

it('skips blank lines', function () {
    importLines(['Birinshi qatar.', '', '   ', 'Ekinshi qatar.']);

    expect(Text::count())->toBe(2)
        ->and(File::sole()->rows_total)->toBe(2);
});

it('labels the dataset it creates', function () {
    importLines(['Birinshi qatar.'], ['--label' => 'v3']);

    expect(File::sole()->label)->toBe('v3');
});

it('leaves the label null when none is given', function () {
    importLines(['Birinshi qatar.']);

    expect(File::sole()->label)->toBeNull();
});

it('rejects a non-positive chunk size before touching the database', function () {
    $exit = importLines(['Birinshi qatar.'], ['--chunk' => 0]);

    expect($exit)->toBe(2)
        ->and(File::count())->toBe(0)
        ->and(Text::count())->toBe(0);
});

it('reports a missing file instead of failing silently', function () {
    $exit = Artisan::call('import:text-file', ['filename' => 'yoq.txt']);

    expect($exit)->toBe(2)
        ->and(File::count())->toBe(0);
});
