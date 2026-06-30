<?php

use App\Enums\AudioSplitStatusEnum;
use App\Enums\RoleEnum;
use App\Models\Audio;
use App\Models\File;
use App\Models\Text;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->file = File::factory()->create();
    config(['dataset.main_file_id' => $this->file->id]);

    Sanctum::actingAs(User::factory()->create(['role' => RoleEnum::ADMIN->value]));
});

/**
 * Build a SPLIT long audio with one child part and return that part's text.
 */
function splitPartText(
    string $transcript = 'old half',
    AudioSplitStatusEnum $parentStatus = AudioSplitStatusEnum::SPLIT,
    ?int $fileId = null,
): Text {
    $parent = Audio::factory()->for(Text::factory()->main(), 'text')->create([
        'edit_converted_audio_duration' => Audio::STT_MAX_DURATION_SAMPLES + 16000,
        'split_status' => $parentStatus,
    ]);

    $childText = Text::factory()->create([
        'file_id' => $fileId ?? config('dataset.main_file_id'),
        'is_split_part' => true,
        'edit_original_transcript' => $transcript,
    ]);

    $parent->splitParts()->create([
        'text_id' => $childText->id,
        'edit_audio_filename' => 'audio/part.wav',
        'edit_converted_audio_duration' => 180000,
        'split_status' => AudioSplitStatusEnum::NONE,
    ]);

    return $childText;
}

it('updates a split part transcript and derives normalized and tokenized forms', function () {
    $text = splitPartText();

    $this->patchJson(route('admin.long.audio.part.text.update', $text), ['text' => 'Hello World.'])
        ->assertOk()
        ->assertJsonPath('data.id', $text->id)
        ->assertJsonPath('data.edit_original_transcript', 'Hello World.')
        ->assertJsonPath('data.edit_normalized_transcript', 'hello world')
        ->assertJsonPath('data.edit_tokenized_transcript', 'h e l l o | w o r l d |');

    expect($text->refresh())
        ->edit_original_transcript->toBe('Hello World.')
        ->edit_normalized_transcript->toBe('hello world')
        ->edit_tokenized_transcript->toBe('h e l l o | w o r l d |');
});

it('records an action with the old and new text', function () {
    $text = splitPartText('old half');

    $this->patchJson(route('admin.long.audio.part.text.update', $text), ['text' => 'new half'])
        ->assertOk();

    $this->assertDatabaseHas('actions', [
        'text_id' => $text->id,
        'user_id' => auth()->id(),
        'old_text' => 'old half',
        'new_text' => 'new half',
    ]);
});

it('leaves the part audio untouched', function () {
    $text = splitPartText();
    $audio = $text->audio()->sole();

    $this->patchJson(route('admin.long.audio.part.text.update', $text), ['text' => 'new half'])
        ->assertOk();

    expect($audio->refresh())
        ->edit_audio_filename->toBe('audio/part.wav')
        ->edit_converted_audio_duration->toBe(180000)
        ->split_status->toBe(AudioSplitStatusEnum::NONE);
});

it('rejects a text that is not a split part', function () {
    $text = Text::factory()->main()->create();

    $this->patchJson(route('admin.long.audio.part.text.update', $text), ['text' => 'x'])
        ->assertNotFound();
});

it('rejects a split part whose parent audio is not split', function () {
    $text = splitPartText('half', AudioSplitStatusEnum::PENDING);

    $this->patchJson(route('admin.long.audio.part.text.update', $text), ['text' => 'x'])
        ->assertNotFound();
});

it('rejects a split part that belongs to another dataset file', function () {
    $otherFile = File::factory()->create();
    $text = splitPartText('half', AudioSplitStatusEnum::SPLIT, $otherFile->id);

    $this->patchJson(route('admin.long.audio.part.text.update', $text), ['text' => 'x'])
        ->assertNotFound();
});

it('requires the text field', function () {
    $text = splitPartText();

    $this->patchJson(route('admin.long.audio.part.text.update', $text), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('text');
});
