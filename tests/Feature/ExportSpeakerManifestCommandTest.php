<?php

use App\Console\Commands\ExportSpeakerManifestCommand;
use App\Enums\AudioSplitStatusEnum;
use App\Enums\GenderEnum;
use App\Enums\RoleEnum;
use App\Models\Audio;
use App\Models\Export;
use App\Models\File;
use App\Models\Text;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'filesystems.disks.yandex-s3.url' => 'https://old.example.test',
        'filesystems.disks.r2.url' => 'https://new.example.test',
    ]);
});

afterEach(function () {
    @unlink(storage_path('app/test_speaker_manifest.tsv'));
});

/**
 * Parse the manifest into rows keyed by column name.
 *
 * @return list<array<string, string>>
 */
function manifestRows(): array
{
    $lines = array_filter(explode(PHP_EOL, file_get_contents(storage_path('app/test_speaker_manifest.tsv'))));
    $header = explode("\t", array_shift($lines));

    return array_values(array_map(fn (string $line): array => array_combine($header, explode("\t", $line)), $lines));
}

it('lists every verified audio across all datasets with its speaker and storage location', function () {
    $active = File::factory()->create();
    $legacy = File::factory()->create();
    config(['dataset.main_file_id' => $active->id]);

    $speaker = User::factory()->create([
        'role' => RoleEnum::SPEAKER->value,
        'gender' => GenderEnum::FEMALE->value,
        'age' => 21,
    ]);

    $activeText = Text::factory()->create([
        'file_id' => $active->id,
        'edit_original_transcript' => 'Sálem, dúnya.',
        'edit_normalized_transcript' => 'sálem, dúnya.',
    ]);
    $legacyText = Text::factory()->create(['file_id' => $legacy->id]);

    $fresh = Audio::factory()->create([
        'text_id' => $activeText->id,
        'edit_speaker_id' => $speaker->id,
        'edit_speaker_gender' => GenderEnum::FEMALE,
        'edit_audio_filename' => 'audio/fresh.wav',
        'storage_disk' => 'r2',
        'edit_converted_audio_filename' => 'audio_conv/fresh.wav',
        'edit_converted_audio_duration' => 32000,
        'speak_finished_at' => '2026-08-13 10:00:00',
        'moderator_finished_at' => '2026-08-14 09:30:00',
    ]);

    $old = Audio::factory()->create([
        'text_id' => $legacyText->id,
        'edit_speaker_id' => $speaker->id,
        'edit_audio_filename' => 'audio/legacy.wav',
        'storage_disk' => null,
        'edit_converted_audio_duration' => null,
    ]);

    Audio::factory()->create([
        'text_id' => $activeText->id,
        'edit_speaker_id' => $speaker->id,
        'edit_audio_filename' => 'audio/rejected.wav',
        'is_correct' => false,
    ]);

    Audio::factory()->create([
        'text_id' => $activeText->id,
        'edit_speaker_id' => $speaker->id,
        'edit_audio_filename' => 'audio/unmoderated.wav',
        'is_correct' => null,
    ]);

    Artisan::call('audio:export-speaker-manifest', ['--filename' => 'test_speaker_manifest.tsv']);

    $rows = manifestRows();

    expect(array_column($rows, 'audio_filename'))->toBe(['audio/fresh.wav', 'audio/legacy.wav'])
        ->and(array_keys($rows[0]))->toBe(ExportSpeakerManifestCommand::COLUMNS);

    expect($rows[0])->toMatchArray([
        'audio_id' => (string) $fresh->id,
        'speaker_id' => (string) $speaker->id,
        'gender' => 'FEMALE',
        'age' => '21',
        'file_id' => (string) $active->id,
        'text_id' => (string) $activeText->id,
        'is_split_part' => '0',
        'storage_disk' => 'r2',
        'audio_url' => 'https://new.example.test/audio/fresh.wav',
        'converted_filename' => 'audio_conv/fresh.wav',
        'duration_samples' => '32000',
        'duration_s' => '2',
        'recorded_at' => '2026-08-13 10:00:00',
        'moderated_at' => '2026-08-14 09:30:00',
        'transcript_original' => 'Sálem, dúnya.',
        'transcript_normalized' => 'sálem, dúnya.',
    ]);

    // A row recorded before storage became configurable resolves to the legacy disk.
    expect($rows[1])->toMatchArray([
        'audio_id' => (string) $old->id,
        'file_id' => (string) $legacy->id,
        'storage_disk' => 'yandex-s3',
        'audio_url' => 'https://old.example.test/audio/legacy.wav',
        'duration_samples' => '',
        'duration_s' => '',
    ]);

    expect(Artisan::output())->toContain('Exported 2 verified audios of 1 speakers');
});

it('drops split parents but keeps their parts', function () {
    $text = Text::factory()->create();
    $part = Text::factory()->create(['file_id' => $text->file_id, 'is_split_part' => true]);

    $parent = Audio::factory()->create([
        'text_id' => $text->id,
        'edit_audio_filename' => 'audio/long.wav',
        'edit_converted_audio_duration' => Audio::STT_MAX_DURATION_SAMPLES,
        'split_status' => AudioSplitStatusEnum::SPLIT,
    ]);

    Audio::factory()->create([
        'text_id' => $part->id,
        'parent_audio_id' => $parent->id,
        'edit_audio_filename' => 'audio/long-part.wav',
        'edit_converted_audio_duration' => 16000,
    ]);

    Audio::factory()->create([
        'text_id' => $text->id,
        'edit_audio_filename' => 'audio/unsplittable.wav',
        'edit_converted_audio_duration' => Audio::STT_MAX_DURATION_SAMPLES,
        'split_status' => AudioSplitStatusEnum::UNSPLITTABLE,
    ]);

    Artisan::call('audio:export-speaker-manifest', ['--filename' => 'test_speaker_manifest.tsv']);

    $rows = manifestRows();

    expect(array_column($rows, 'audio_filename'))->toBe(['audio/long-part.wav', 'audio/unsplittable.wav'])
        ->and($rows[0]['parent_audio_id'])->toBe((string) $parent->id)
        ->and($rows[0]['is_split_part'])->toBe('1');
});

it('is a view, not an export step: nothing is stamped and no export is created', function () {
    $audio = Audio::factory()->create(['exported_at' => null, 'export_id' => null]);

    Artisan::call('audio:export-speaker-manifest', ['--filename' => 'test_speaker_manifest.tsv']);

    expect(Export::count())->toBe(0)
        ->and($audio->fresh()->exported_at)->toBeNull()
        ->and($audio->fresh()->export_id)->toBeNull();
});

it('keeps transcripts on one line so tabs and line breaks cannot break the tsv', function () {
    $text = Text::factory()->create([
        'edit_original_transcript' => "Bir\tekі\r\núsh",
        'edit_normalized_transcript' => null,
    ]);
    Audio::factory()->create(['text_id' => $text->id]);

    Artisan::call('audio:export-speaker-manifest', ['--filename' => 'test_speaker_manifest.tsv']);

    $rows = manifestRows();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['transcript_original'])->toBe('Bir ekі úsh')
        ->and($rows[0]['transcript_normalized'])->toBe('');
});

it('fails cleanly when the output file cannot be written', function () {
    $exit = Artisan::call('audio:export-speaker-manifest', ['--filename' => 'no-such-dir/test_speaker_manifest.tsv']);

    expect($exit)->toBe(1)
        ->and(Artisan::output())->toContain('Cannot write to');
});
