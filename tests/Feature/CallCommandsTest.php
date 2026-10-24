<?php

use App\Enums\CallStatusEnum;
use App\Models\CallRecording;
use App\Models\Export;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('calls');
});

afterEach(function () {
    foreach (['calls_test.tsv', 'calls_test_files.txt'] as $file) {
        @unlink(storage_path('app/'.$file));
    }
});

it('registers the uploaded calls once, with their size', function () {
    Storage::disk('calls')->put('source/oct/a.mp3', '12345');
    Storage::disk('calls')->put('source/oct/sub/b.WAV', '1234567');
    Storage::disk('calls')->put('source/oct/notes.txt', 'x');
    Storage::disk('calls')->put('source/oct/c.amr', 'x');
    Storage::disk('calls')->put('elsewhere/d.mp3', 'x');

    $this->artisan('calls:register', ['--prefix' => 'source/oct'])
        ->expectsOutputToContain('Found 2 audio files under "source/oct", registered 2 new calls (batch: oct)')
        ->expectsOutputToContain('amr: 1')
        ->assertSuccessful();

    $calls = CallRecording::query()->orderBy('source_path')->get();

    expect($calls)->toHaveCount(2)
        ->and($calls[0]->source_path)->toBe('source/oct/a.mp3')
        ->and($calls[0]->original_filename)->toBe('a.mp3')
        ->and($calls[0]->size_bytes)->toBe(5)
        ->and($calls[0]->batch)->toBe('oct')
        ->and($calls[0]->status)->toBe(CallStatusEnum::PENDING)
        ->and($calls[1]->source_path)->toBe('source/oct/sub/b.WAV');

    Storage::disk('calls')->put('source/oct/e.ogg', 'x');

    $this->artisan('calls:register', ['--prefix' => 'source/oct', '--batch' => 'october'])
        ->expectsOutputToContain('Found 3 audio files under "source/oct", registered 1 new calls (batch: october)')
        ->assertSuccessful();

    expect(CallRecording::query()->count())->toBe(3)
        ->and(CallRecording::query()->where('source_path', 'source/oct/a.mp3')->value('batch'))->toBe('oct');
});

it('exports approved calls in the dataset format and stamps them', function () {
    $trimmer = User::factory()->trimmer()->create();
    $call = CallRecording::factory()->approved($trimmer)->create([
        'trimmed_path' => 'trimmed/7_abc.wav',
        'trimmed_duration' => 48000,
        'transcript' => 'Sálem, "dos"',
        'normalized_transcript' => 'sálem, "dos"',
        'tokenized_transcript' => 's á l e m , | " d o s " |',
    ]);
    CallRecording::factory()->submitted($trimmer)->create();
    CallRecording::factory()->approved($trimmer)->create(['exported_at' => now()]);

    $this->artisan('calls:export', ['--filename' => 'calls_test.tsv'])
        ->expectsOutputToContain('Exported 1 approved calls')
        ->assertSuccessful();

    expect(file_get_contents(storage_path('app/calls_test.tsv')))
        ->toBe(implode("\t", ['call-'.$call->id, '7_abc.wav', 'Sálem, dos', 'sálem, dos', 's á l e m , | d o s |', 48000, '']).PHP_EOL)
        ->and(file_get_contents(storage_path('app/calls_test_files.txt')))
        ->toBe('call-'.$call->id.';trimmed/7_abc.wav'.PHP_EOL);

    $export = Export::query()->sole();

    expect($export->name)->toBe('calls batch '.now()->format('Y-m'))
        ->and($export->exported_count)->toBe(1)
        ->and($call->refresh()->exported_at)->not->toBeNull()
        ->and($call->export_id)->toBe($export->id);

    $this->artisan('calls:export', ['--filename' => 'calls_test.tsv'])
        ->expectsOutputToContain('Exported 0 approved calls')
        ->assertSuccessful();

    expect(Export::query()->count())->toBe(1);
});

it('re-dumps everything with --all without touching the export history', function () {
    CallRecording::factory()->approved()->create(['exported_at' => now()]);
    CallRecording::factory()->approved()->create();

    $this->artisan('calls:export', ['--filename' => 'calls_test.tsv', '--all' => true])
        ->expectsOutputToContain('Exported 2 approved calls')
        ->assertSuccessful();

    expect(Export::query()->count())->toBe(0)
        ->and(CallRecording::query()->whereNull('exported_at')->count())->toBe(1);
});
