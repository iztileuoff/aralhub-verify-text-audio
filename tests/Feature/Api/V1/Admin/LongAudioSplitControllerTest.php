<?php

use App\Enums\AudioSplitStatusEnum;
use App\Enums\GenderEnum;
use App\Enums\RoleEnum;
use App\Models\Audio;
use App\Models\File;
use App\Models\Text;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('yandex-s3');

    Sanctum::actingAs(User::factory()->create(['role' => RoleEnum::ADMIN->value]));
});

function splitPayload(): array
{
    return [
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
    ];
}

it('splits a long audio into two shorter audios with their own texts', function () {
    $file = File::factory()->create();
    $text = Text::factory()->create(['file_id' => $file->id]);
    $audio = Audio::factory()->pendingSplit()->create([
        'text_id' => $text->id,
        'edit_speaker_gender' => GenderEnum::MALE,
        'is_correct' => true,
    ]);

    $this->post(route('admin.long.audio.split', $audio), splitPayload(), ['Accept' => 'application/json'])
        ->assertCreated();

    expect($audio->refresh()->split_status)->toBe(AudioSplitStatusEnum::SPLIT);

    $parts = $audio->splitParts()->with('text')->orderBy('id')->get();

    expect($parts)->toHaveCount(2)
        ->and($parts[0]->edit_converted_audio_duration)->toBe(200000)
        ->and($parts[1]->edit_converted_audio_duration)->toBe(180000)
        ->and($parts[0]->split_status)->toBe(AudioSplitStatusEnum::NONE)
        ->and($parts[0]->edit_speaker_gender)->toBe(GenderEnum::MALE)
        ->and($parts[0]->is_correct)->toBeTrue()
        ->and($parts[0]->text->edit_original_transcript)->toBe('first half')
        ->and($parts[1]->text->edit_original_transcript)->toBe('second half')
        ->and($parts[0]->text->file_id)->toBe($file->id)
        ->and($parts[0]->text->is_split_part)->toBeTrue()
        ->and($parts[1]->text->is_split_part)->toBeTrue()
        ->and($parts[0]->text->id)->not->toBe($text->id);

    Storage::disk('yandex-s3')->assertExists($parts[0]->edit_audio_filename);
    Storage::disk('yandex-s3')->assertExists($parts[1]->edit_audio_filename);
});

it('rejects a payload that is not exactly two parts', function () {
    $audio = Audio::factory()->pendingSplit()->create();

    $payload = splitPayload();
    unset($payload['parts'][1]);

    $this->post(route('admin.long.audio.split', $audio), $payload, ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('parts');

    expect($audio->refresh()->split_status)->toBe(AudioSplitStatusEnum::PENDING)
        ->and($audio->splitParts()->count())->toBe(0);
});

it('rejects a part that is still 30s or longer', function () {
    $audio = Audio::factory()->pendingSplit()->create();

    $payload = splitPayload();
    $payload['parts'][0]['duration'] = Audio::STT_MAX_DURATION_SAMPLES;

    $this->post(route('admin.long.audio.split', $audio), $payload, ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('parts.0.duration');

    expect($audio->splitParts()->count())->toBe(0);
});

it('refuses to split an audio shorter than the STT limit', function () {
    $audio = Audio::factory()->create([
        'edit_converted_audio_duration' => Audio::STT_MAX_DURATION_SAMPLES - 1,
    ]);

    $this->post(route('admin.long.audio.split', $audio), splitPayload(), ['Accept' => 'application/json'])
        ->assertStatus(422);

    expect($audio->refresh()->split_status)->toBe(AudioSplitStatusEnum::NONE)
        ->and($audio->splitParts()->count())->toBe(0);
});

it('refuses to split an audio that is already split', function () {
    $audio = Audio::factory()->pendingSplit()->create([
        'split_status' => AudioSplitStatusEnum::SPLIT,
    ]);

    $this->post(route('admin.long.audio.split', $audio), splitPayload(), ['Accept' => 'application/json'])
        ->assertStatus(422);

    expect($audio->splitParts()->count())->toBe(0);
});
