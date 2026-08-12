<?php

use App\Models\Audio;
use App\Models\File;
use App\Models\Text;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

afterEach(function () {
    @unlink(storage_path('app/test_audio_filenames.txt'));
});

it('exports only correct audios without a converted duration for the given file', function () {
    $file = File::factory()->create();
    $otherFile = File::factory()->create();

    $text = Text::factory()->create(['file_id' => $file->id]);
    $otherText = Text::factory()->create(['file_id' => $otherFile->id]);

    $candidate = Audio::factory()->create([
        'text_id' => $text->id,
        'edit_audio_filename' => 'audio/candidate.mp3',
        'is_correct' => true,
        'edit_converted_audio_duration' => null,
    ]);

    Audio::factory()->create([
        'text_id' => $text->id,
        'edit_audio_filename' => 'audio/incorrect.mp3',
        'is_correct' => false,
        'edit_converted_audio_duration' => null,
    ]);

    Audio::factory()->create([
        'text_id' => $text->id,
        'edit_audio_filename' => 'audio/withduration.mp3',
        'is_correct' => true,
        'edit_converted_audio_duration' => 1200,
    ]);

    Audio::factory()->create([
        'text_id' => $otherText->id,
        'edit_audio_filename' => 'audio/otherfile.mp3',
        'is_correct' => true,
        'edit_converted_audio_duration' => null,
    ]);

    Artisan::call('audio:export-filenames', [
        '--filename' => 'test_audio_filenames.txt',
        '--file-id' => $file->id,
    ]);

    $contents = file_get_contents(storage_path('app/test_audio_filenames.txt'));

    expect($contents)
        ->toContain("{$candidate->id};candidate.mp3")
        ->not->toContain('audio/')
        ->not->toContain('incorrect.mp3')
        ->not->toContain('withduration.mp3')
        ->not->toContain('otherfile.mp3');
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
        'edit_converted_audio_duration' => null,
    ]);

    Audio::factory()->create([
        'text_id' => $legacyText->id,
        'edit_audio_filename' => 'audio/legacy.mp3',
        'is_correct' => true,
        'edit_converted_audio_duration' => null,
    ]);

    Artisan::call('audio:export-filenames', ['--filename' => 'test_audio_filenames.txt']);

    expect(file_get_contents(storage_path('app/test_audio_filenames.txt')))
        ->toContain('active.mp3')
        ->not->toContain('legacy.mp3');

    // An explicit id still reaches the previous dataset — that is how its backlog is finished.
    Artisan::call('audio:export-filenames', [
        '--filename' => 'test_audio_filenames.txt',
        '--file-id' => $legacyText->file_id,
    ]);

    expect(file_get_contents(storage_path('app/test_audio_filenames.txt')))
        ->toContain('legacy.mp3')
        ->not->toContain('active.mp3');
});
