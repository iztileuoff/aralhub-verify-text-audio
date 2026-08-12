<?php

use App\Enums\GenderEnum;
use App\Enums\RoleEnum;
use App\Models\Audio;
use App\Models\Text;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Two disks with fixed public URLs, so a resolved URL says unambiguously
 * which disk the record was read from.
 */
beforeEach(function () {
    config([
        'filesystems.disks.old-storage' => [
            'driver' => 'local',
            'root' => storage_path('framework/testing/old-storage'),
            'url' => 'https://old.example.test',
        ],
        'filesystems.disks.new-storage' => [
            'driver' => 'local',
            'root' => storage_path('framework/testing/new-storage'),
            'url' => 'https://new.example.test',
        ],
        'audio.disk' => 'new-storage',
        'audio.legacy_disk' => 'old-storage',
    ]);
});

it('reads audio recorded before the switch from the legacy disk', function () {
    $audio = Audio::factory()->create([
        'edit_audio_filename' => 'audio/legacy.mp3',
        'storage_disk' => null,
    ]);

    expect($audio->audioDisk())->toBe('old-storage')
        ->and($audio->edit_audio_url)->toBe('https://old.example.test/audio/legacy.mp3');
});

it('reads audio from the disk stamped on the record', function () {
    $audio = Audio::factory()->create([
        'edit_audio_filename' => 'audio/fresh.mp3',
        'storage_disk' => 'new-storage',
    ]);

    expect($audio->audioDisk())->toBe('new-storage')
        ->and($audio->edit_audio_url)->toBe('https://new.example.test/audio/fresh.mp3');
});

it('resolves the text audio url through the same per-record disk', function () {
    $legacy = Text::factory()->create([
        'edit_audio_filename' => 'audio/legacy.mp3',
        'storage_disk' => null,
    ]);

    $fresh = Text::factory()->create([
        'edit_audio_filename' => 'audio/fresh.mp3',
        'storage_disk' => 'new-storage',
    ]);

    expect($legacy->edit_audio_url)->toBe('https://old.example.test/audio/legacy.mp3')
        ->and($fresh->edit_audio_url)->toBe('https://new.example.test/audio/fresh.mp3');
});

it('writes a new recording to the configured disk and stamps it', function () {
    Storage::fake('new-storage');

    $user = User::factory()->create([
        'role' => RoleEnum::SPEAKER->value,
        'gender' => GenderEnum::MALE->value,
    ]);

    Sanctum::actingAs($user);

    $text = Text::factory()->create([
        'speak_started_at' => now()->subMinutes(5),
        'edit_speaker_id' => $user->id,
    ]);

    $this->postJson(
        route('admin.verify.speak.text.audio.complete', $text),
        ['audio' => UploadedFile::fake()->create('audio.mp3', 100, 'audio/mpeg')]
    )->assertSuccessful();

    $audio = $text->audio()->sole();

    expect($audio->storage_disk)->toBe('new-storage');

    Storage::disk('new-storage')->assertExists($audio->edit_audio_filename);
});

it('deletes the replaced file from the disk it was actually stored on', function () {
    Storage::fake('old-storage');
    Storage::fake('new-storage');

    Storage::disk('old-storage')->put('audio/legacy.mp3', 'old');

    $user = User::factory()->create([
        'role' => RoleEnum::SPEAKER->value,
        'gender' => GenderEnum::MALE->value,
    ]);

    Sanctum::actingAs($user);

    $text = Text::factory()->create([
        'edit_audio_filename' => 'audio/legacy.mp3',
        'storage_disk' => null,
    ]);

    $this->postJson(
        route('admin.verify.speak.text.audio.complete', $text),
        ['audio' => UploadedFile::fake()->create('audio.mp3', 100, 'audio/mpeg')]
    )->assertSuccessful();

    Storage::disk('old-storage')->assertMissing('audio/legacy.mp3');

    Storage::disk('new-storage')->assertExists($text->audio()->sole()->edit_audio_filename);
});

it('stamps split parts with the configured disk', function () {
    Storage::fake('new-storage');

    Sanctum::actingAs(User::factory()->create(['role' => RoleEnum::ADMIN->value]));

    $audio = Audio::factory()->pendingSplit()->create([
        'text_id' => Text::factory()->create()->id,
        'storage_disk' => null,
    ]);

    $this->post(route('admin.long.audio.split', $audio), [
        'parts' => [
            [
                'audio' => UploadedFile::fake()->create('part1.wav', 100, 'audio/x-wav'),
                'duration' => 200000,
                'original_transcript' => 'first half',
            ],
            [
                'audio' => UploadedFile::fake()->create('part2.wav', 100, 'audio/x-wav'),
                'duration' => 180000,
                'original_transcript' => 'second half',
            ],
        ],
    ], ['Accept' => 'application/json'])->assertCreated();

    $parts = $audio->splitParts()->orderBy('id')->get();

    expect($parts)->toHaveCount(2)
        ->and($parts[0]->storage_disk)->toBe('new-storage')
        ->and($parts[1]->storage_disk)->toBe('new-storage');

    Storage::disk('new-storage')->assertExists($parts[0]->edit_audio_filename);
});
