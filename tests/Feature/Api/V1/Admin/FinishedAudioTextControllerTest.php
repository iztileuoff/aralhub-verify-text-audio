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
    Storage::fake(config('audio.disk'));
    Storage::fake(config('audio.legacy_disk'));

    $this->file = File::factory()->create();
    config(['dataset.main_file_id' => $this->file->id]);

    Sanctum::actingAs(User::factory()->create(['role' => RoleEnum::ADMIN->value]));
});

it('orders texts by their most recent finished audio first', function () {
    $older = Text::factory()->main()->create();
    Audio::factory()->create(['text_id' => $older->id, 'speak_finished_at' => now()->subDays(5)]);

    $newer = Text::factory()->main()->create();
    Audio::factory()->create(['text_id' => $newer->id, 'speak_finished_at' => now()->subDay()]);
    Audio::factory()->create(['text_id' => $newer->id, 'speak_finished_at' => now()->subDays(10)]);

    $ids = $this->getJson(route('admin.finished.audio.texts'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$newer->id, $older->id]);
});

it('includes texts without audio after those with finished audio', function () {
    $withAudio = Text::factory()->main()->create();
    Audio::factory()->create(['text_id' => $withAudio->id, 'speak_finished_at' => now()]);

    $withoutAudio = Text::factory()->main()->create();

    $ids = $this->getJson(route('admin.finished.audio.texts'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$withAudio->id, $withoutAudio->id]);
});

it('lists only the active dataset by default', function () {
    $active = Text::factory()->main()->create();
    Audio::factory()->create(['text_id' => $active->id, 'speak_finished_at' => now()]);

    $legacy = Text::factory()->create(['is_main' => true]);
    Audio::factory()->create(['text_id' => $legacy->id, 'speak_finished_at' => now()]);

    $splitPart = Text::factory()->create(['file_id' => $this->file->id, 'is_split_part' => true]);
    Audio::factory()->create(['text_id' => $splitPart->id, 'speak_finished_at' => now()]);

    $ids = $this->getJson(route('admin.finished.audio.texts'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$active->id]);
});

it('reaches an older dataset when a file id is given', function () {
    $legacyFile = File::factory()->create();

    $legacy = Text::factory()->create(['file_id' => $legacyFile->id, 'is_main' => true]);
    Audio::factory()->create(['text_id' => $legacy->id, 'speak_finished_at' => now()]);

    $active = Text::factory()->main()->create();
    Audio::factory()->create(['text_id' => $active->id, 'speak_finished_at' => now()]);

    $ids = $this->getJson(route('admin.finished.audio.texts', ['file_id' => $legacyFile->id]))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$legacy->id]);
});

it('filters by speaker id via the audio relation', function () {
    $speaker = User::factory()->create(['role' => RoleEnum::SPEAKER->value]);

    $target = Text::factory()->main()->create();
    Audio::factory()->create([
        'text_id' => $target->id,
        'edit_speaker_id' => $speaker->id,
        'speak_finished_at' => now(),
    ]);

    $other = Text::factory()->main()->create();
    Audio::factory()->create(['text_id' => $other->id, 'speak_finished_at' => now()]);

    $ids = $this->getJson(route('admin.finished.audio.texts', ['speaker_id' => $speaker->id]))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$target->id]);
});

it('filters by search on the text id', function () {
    $target = Text::factory()->main()->create();
    Audio::factory()->create(['text_id' => $target->id, 'speak_finished_at' => now()]);

    Text::factory()->main()->create();

    $ids = $this->getJson(route('admin.finished.audio.texts', ['search' => $target->id]))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$target->id]);
});

it('respects the per_page parameter', function () {
    Text::factory()->main()->count(3)->create()->each(
        fn (Text $text) => Audio::factory()->create([
            'text_id' => $text->id,
            'speak_finished_at' => now(),
        ])
    );

    $response = $this->getJson(route('admin.finished.audio.texts', ['per_page' => 2]))->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('meta.per_page'))->toBe(2);
});
