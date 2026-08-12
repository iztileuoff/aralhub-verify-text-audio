<?php

use App\Enums\RoleEnum;
use App\Models\Audio;
use App\Models\File;
use App\Models\Text;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('yandex-s3');

    $this->file = File::factory()->create();
    config(['dataset.main_file_id' => $this->file->id]);

    $this->moderator = User::factory()->create(['role' => RoleEnum::MODERATOR->value]);
    Sanctum::actingAs($this->moderator);
});

/** Attributes of an audio waiting in the moderation queue. */
function queuedAudio(int $textId): array
{
    return [
        'text_id' => $textId,
        'speak_finished_at' => now(),
        'moderator_started_at' => null,
        'moderator_id' => null,
        'is_correct' => null,
    ];
}

it('assigns only audios of the active dataset file', function () {
    $otherText = Text::factory()->create();
    Audio::factory()->count(3)->create(queuedAudio($otherText->id));

    $mainText = Text::factory()->main()->create();
    $target = Audio::factory()->create(queuedAudio($mainText->id));

    $id = $this->getJson(route('admin.verify.moderator.audio'))->assertOk()->json('data.id');

    expect($id)->toBe($target->id);
});

it('reports an empty queue when only foreign-dataset audios are waiting', function () {
    $otherText = Text::factory()->create();
    Audio::factory()->create(queuedAudio($otherText->id));

    $this->getJson(route('admin.verify.moderator.audio'))
        ->assertNotFound()
        ->assertJson(['message' => 'Аудиозаписи отсутствуют.']);
});

it('releases a hold left on a foreign-dataset audio and hands out a current one', function () {
    $otherText = Text::factory()->create();

    $held = Audio::factory()->create(array_merge(queuedAudio($otherText->id), [
        'moderator_id' => $this->moderator->id,
        'moderator_started_at' => now()->subHour(),
    ]));

    $mainText = Text::factory()->main()->create();
    $target = Audio::factory()->create(queuedAudio($mainText->id));

    $id = $this->getJson(route('admin.verify.moderator.audio'))->assertOk()->json('data.id');

    $held->refresh();

    expect($id)->toBe($target->id)
        ->and($held->moderator_id)->toBeNull()
        ->and($held->moderator_started_at)->toBeNull();
});
