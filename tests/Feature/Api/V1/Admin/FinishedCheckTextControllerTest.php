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

    Sanctum::actingAs(User::factory()->create(['role' => RoleEnum::ADMIN->value]));
});

it('lists the most recently moderated audio of the active dataset first', function () {
    $text = Text::factory()->main()->create();

    $older = Audio::factory()->create(['text_id' => $text->id, 'moderator_finished_at' => now()->subDays(2)]);
    $newer = Audio::factory()->create(['text_id' => $text->id, 'moderator_finished_at' => now()]);

    Audio::factory()->create(['text_id' => $text->id, 'is_correct' => null]);

    $ids = $this->getJson(route('admin.finished.check.texts'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$newer->id, $older->id]);
});

it('leaves out audio of other datasets by default', function () {
    $active = Audio::factory()->create([
        'text_id' => Text::factory()->main(),
        'moderator_finished_at' => now(),
    ]);

    Audio::factory()->create([
        'text_id' => Text::factory()->create(['is_main' => true]),
        'moderator_finished_at' => now(),
    ]);

    $ids = $this->getJson(route('admin.finished.check.texts'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$active->id]);
});

it('reaches an older dataset when a file id is given', function () {
    $legacyFile = File::factory()->create();

    $legacy = Audio::factory()->create([
        'text_id' => Text::factory()->create(['file_id' => $legacyFile->id, 'is_main' => true]),
        'moderator_finished_at' => now(),
    ]);

    Audio::factory()->create([
        'text_id' => Text::factory()->main(),
        'moderator_finished_at' => now(),
    ]);

    $ids = $this->getJson(route('admin.finished.check.texts', ['file_id' => $legacyFile->id]))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$legacy->id]);
});

it('filters by the moderator', function () {
    $moderator = User::factory()->create(['role' => RoleEnum::MODERATOR->value]);
    $text = Text::factory()->main()->create();

    $target = Audio::factory()->create([
        'text_id' => $text->id,
        'moderator_id' => $moderator->id,
        'moderator_finished_at' => now(),
    ]);

    Audio::factory()->create(['text_id' => $text->id, 'moderator_finished_at' => now()]);

    $ids = $this->getJson(route('admin.finished.check.texts', ['moderator_id' => $moderator->id]))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$target->id]);
});
