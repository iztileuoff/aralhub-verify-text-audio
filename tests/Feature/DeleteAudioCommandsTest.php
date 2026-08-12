<?php

use App\Models\Audio;
use App\Models\Text;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * The originals live on an object storage disk, the converted copies on the
 * local public disk — the delete commands have to reach both.
 */
beforeEach(function () {
    Storage::fake('public');
    Storage::fake('yandex-s3');
    Storage::fake('r2');

    config(['audio.legacy_disk' => 'yandex-s3']);
});

/**
 * @param  string|null  $disk  Value for storage_disk; null means "recorded before the switch".
 */
function audioWithFiles(?string $disk, array $attributes = []): Audio
{
    $audio = Audio::factory()->create([
        'edit_audio_filename' => 'audio/'.fake()->unique()->lexify('??????????').'.mp3',
        'edit_converted_audio_filename' => 'audio_conv/'.fake()->unique()->lexify('??????????').'.wav',
        'storage_disk' => $disk,
        ...$attributes,
    ]);

    Storage::disk($disk ?? 'yandex-s3')->put($audio->edit_audio_filename, 'original');
    Storage::disk('public')->put($audio->edit_converted_audio_filename, 'converted');

    return $audio;
}

it('deletes a speaker\'s original from the legacy disk and the converted copy locally', function () {
    $user = User::factory()->create();
    $audio = audioWithFiles(null, ['edit_speaker_id' => $user->id]);

    $this->artisan('audio:delete', ['user_id' => $user->id])->assertSuccessful();

    Storage::disk('yandex-s3')->assertMissing($audio->edit_audio_filename);
    Storage::disk('public')->assertMissing($audio->edit_converted_audio_filename);

    expect(Audio::find($audio->id))->toBeNull();
});

it('deletes the original from the disk stamped on the record', function () {
    $user = User::factory()->create();
    $audio = audioWithFiles('r2', ['edit_speaker_id' => $user->id]);

    $this->artisan('audio:delete', ['user_id' => $user->id])->assertSuccessful();

    Storage::disk('r2')->assertMissing($audio->edit_audio_filename);
});

it('leaves other speakers files alone', function () {
    $target = User::factory()->create();
    $other = User::factory()->create();

    audioWithFiles(null, ['edit_speaker_id' => $target->id]);
    $kept = audioWithFiles(null, ['edit_speaker_id' => $other->id]);

    $this->artisan('audio:delete', ['user_id' => $target->id])->assertSuccessful();

    Storage::disk('yandex-s3')->assertExists($kept->edit_audio_filename);
    Storage::disk('public')->assertExists($kept->edit_converted_audio_filename);

    expect(Audio::find($kept->id))->not->toBeNull();
});

it('refreshes audio_count on the text after deleting', function () {
    $user = User::factory()->create();
    $text = Text::factory()->create(['audio_count' => 2]);

    audioWithFiles(null, ['edit_speaker_id' => $user->id, 'text_id' => $text->id]);
    audioWithFiles(null, ['edit_speaker_id' => User::factory()->create()->id, 'text_id' => $text->id]);

    $this->artisan('audio:delete', ['user_id' => $user->id])->assertSuccessful();

    expect($text->refresh()->audio_count)->toBe(1);
});

it('deletes only the incorrect recordings, from their own disks', function () {
    $incorrectLegacy = audioWithFiles(null, ['is_correct' => false]);
    $incorrectStamped = audioWithFiles('r2', ['is_correct' => false]);
    $correct = audioWithFiles(null, ['is_correct' => true]);

    $this->artisan('audio:delete-incorrect')->assertSuccessful();

    Storage::disk('yandex-s3')->assertMissing($incorrectLegacy->edit_audio_filename);
    Storage::disk('public')->assertMissing($incorrectLegacy->edit_converted_audio_filename);
    Storage::disk('r2')->assertMissing($incorrectStamped->edit_audio_filename);

    Storage::disk('yandex-s3')->assertExists($correct->edit_audio_filename);
    Storage::disk('public')->assertExists($correct->edit_converted_audio_filename);

    expect(Audio::pluck('id')->all())->toBe([$correct->id]);
});
