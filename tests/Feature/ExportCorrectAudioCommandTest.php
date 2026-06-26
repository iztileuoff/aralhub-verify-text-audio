<?php

use App\Models\Audio;
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

    expect($new->fresh()->exported_at)->not->toBeNull();
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

    expect(trim(file_get_contents(storage_path('app/test_correct.tsv'))))->toBe('');

    Artisan::call('audio:export-correct', $options + ['--all' => true]);

    expect(file_get_contents(storage_path('app/test_correct.tsv')))->toContain('first.wav');
});
