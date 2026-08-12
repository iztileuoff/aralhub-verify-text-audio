<?php

use App\Enums\AudioSplitStatusEnum;
use App\Models\Audio;
use App\Models\Export;
use App\Models\File;
use App\Models\Text;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

afterEach(function () {
    @unlink(storage_path('app/test_correct.tsv'));
});

it('exports only new correct audios and stamps them as exported', function () {
    $file = File::factory()->create();
    $text = Text::factory()->create(['file_id' => $file->id]);

    $new = Audio::factory()->create([
        'text_id' => $text->id,
        'edit_audio_filename' => 'audio/exportable.mp3',
        'is_correct' => true,
        'edit_converted_audio_duration' => 100,
        'exported_at' => null,
    ]);

    Audio::factory()->create([
        'text_id' => $text->id,
        'edit_audio_filename' => 'audio/already.mp3',
        'is_correct' => true,
        'edit_converted_audio_duration' => 100,
        'exported_at' => now(),
    ]);

    Audio::factory()->create([
        'text_id' => $text->id,
        'edit_audio_filename' => 'audio/incorrect.mp3',
        'is_correct' => false,
        'edit_converted_audio_duration' => 100,
        'exported_at' => null,
    ]);

    Audio::factory()->create([
        'text_id' => $text->id,
        'edit_audio_filename' => 'audio/noduration.mp3',
        'is_correct' => true,
        'edit_converted_audio_duration' => null,
        'exported_at' => null,
    ]);

    Artisan::call('audio:export-correct', [
        '--filename' => 'test_correct.tsv',
        '--file-id' => $file->id,
    ]);

    $contents = file_get_contents(storage_path('app/test_correct.tsv'));

    expect($contents)
        ->toContain('exportable.wav')
        ->not->toContain('already.wav')
        ->not->toContain('incorrect.wav')
        ->not->toContain('noduration.wav');

    $export = Export::sole();

    expect($export->filename)->toBe('test_correct.tsv')
        ->and($export->exported_count)->toBe(1)
        ->and($new->fresh()->exported_at)->not->toBeNull()
        ->and($new->fresh()->export_id)->toBe($export->id);
});

it('excludes audios at or beyond the 30s limit but exports their shorter split parts', function () {
    $file = File::factory()->create();
    $text = Text::factory()->create(['file_id' => $file->id]);

    Audio::factory()->create([
        'text_id' => $text->id,
        'edit_audio_filename' => 'audio/toolong.mp3',
        'is_correct' => true,
        'edit_converted_audio_duration' => Audio::STT_MAX_DURATION_SAMPLES,
        'split_status' => AudioSplitStatusEnum::SPLIT,
        'exported_at' => null,
    ]);

    Audio::factory()->create([
        'text_id' => $text->id,
        'edit_audio_filename' => 'audio/half.mp3',
        'is_correct' => true,
        'edit_converted_audio_duration' => Audio::STT_MAX_DURATION_SAMPLES - 1,
        'exported_at' => null,
    ]);

    Artisan::call('audio:export-correct', [
        '--filename' => 'test_correct.tsv',
        '--file-id' => $file->id,
    ]);

    $contents = file_get_contents(storage_path('app/test_correct.tsv'));

    expect($contents)
        ->toContain('half.wav')
        ->not->toContain('toolong.wav');
});

it('falls back to the active dataset when --file-id is omitted', function () {
    $active = File::factory()->create();
    config(['dataset.main_file_id' => $active->id]);

    $activeText = Text::factory()->main()->create();
    $legacyText = Text::factory()->create();

    Audio::factory()->create([
        'text_id' => $activeText->id,
        'edit_audio_filename' => 'audio/active.mp3',
        'is_correct' => true,
        'edit_converted_audio_duration' => 100,
        'exported_at' => null,
    ]);

    Audio::factory()->create([
        'text_id' => $legacyText->id,
        'edit_audio_filename' => 'audio/legacy.mp3',
        'is_correct' => true,
        'edit_converted_audio_duration' => 100,
        'exported_at' => null,
    ]);

    Artisan::call('audio:export-correct', ['--filename' => 'test_correct.tsv']);

    expect(file_get_contents(storage_path('app/test_correct.tsv')))
        ->toContain('active.wav')
        ->not->toContain('legacy.wav');

    // The backlog of the previous dataset is still reachable by an explicit id.
    Artisan::call('audio:export-correct', [
        '--filename' => 'test_correct.tsv',
        '--file-id' => $legacyText->file_id,
    ]);

    expect(file_get_contents(storage_path('app/test_correct.tsv')))
        ->toContain('legacy.wav')
        ->not->toContain('active.wav')
        ->and(Export::count())->toBe(2);
});

it('names the export group after the dataset and the month', function () {
    $file = File::factory()->create(['label' => 'v3']);
    $text = Text::factory()->create(['file_id' => $file->id]);

    Audio::factory()->create([
        'text_id' => $text->id,
        'edit_audio_filename' => 'audio/first.mp3',
        'is_correct' => true,
        'edit_converted_audio_duration' => 100,
        'exported_at' => null,
    ]);

    Artisan::call('audio:export-correct', [
        '--filename' => 'test_correct.tsv',
        '--file-id' => $file->id,
    ]);

    expect(Export::sole()->name)->toBe('v3 batch '.now()->format('Y-m'));
});

it('falls back to the dataset id when it has no label, and yields to an explicit --name', function () {
    $file = File::factory()->create(['label' => null]);
    $text = Text::factory()->create(['file_id' => $file->id]);

    $record = fn (string $name) => Audio::factory()->create([
        'text_id' => $text->id,
        'edit_audio_filename' => "audio/{$name}.mp3",
        'is_correct' => true,
        'edit_converted_audio_duration' => 100,
        'exported_at' => null,
    ]);

    $options = ['--filename' => 'test_correct.tsv', '--file-id' => $file->id];

    $record('first');
    Artisan::call('audio:export-correct', $options + ['--name' => 'v2 final']);

    $record('second');
    Artisan::call('audio:export-correct', $options);

    expect(Export::query()->pluck('name')->all())
        ->toBe(['v2 final', "file {$file->id} batch ".now()->format('Y-m')]);
});

it('skips already exported audios on re-run and re-exports everything with --all', function () {
    $file = File::factory()->create();
    $text = Text::factory()->create(['file_id' => $file->id]);

    Audio::factory()->create([
        'text_id' => $text->id,
        'edit_audio_filename' => 'audio/first.mp3',
        'is_correct' => true,
        'edit_converted_audio_duration' => 100,
        'exported_at' => null,
    ]);

    $options = ['--filename' => 'test_correct.tsv', '--file-id' => $file->id];

    Artisan::call('audio:export-correct', $options);
    Artisan::call('audio:export-correct', $options);

    // Second run had nothing new: empty tsv and no extra export record.
    expect(trim(file_get_contents(storage_path('app/test_correct.tsv'))))->toBe('')
        ->and(Export::count())->toBe(1);

    Artisan::call('audio:export-correct', $options + ['--all' => true]);

    // --all re-dumps but does not create an export or change tracking.
    expect(file_get_contents(storage_path('app/test_correct.tsv')))->toContain('first.wav')
        ->and(Export::count())->toBe(1);
});
