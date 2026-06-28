<?php

use App\Enums\AudioSplitStatusEnum;
use App\Enums\RoleEnum;
use App\Models\Audio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('yandex-s3');

    Sanctum::actingAs(User::factory()->create(['role' => RoleEnum::ADMIN->value]));
});

it('marks a long audio as unsplittable', function () {
    $audio = Audio::factory()->pendingSplit()->create();

    $this->postJson(route('admin.long.audio.unsplittable', $audio))->assertOk();

    expect($audio->refresh()->split_status)->toBe(AudioSplitStatusEnum::UNSPLITTABLE);
});

it('does not overwrite an already split audio', function () {
    $audio = Audio::factory()->pendingSplit()->create([
        'split_status' => AudioSplitStatusEnum::SPLIT,
    ]);

    $this->postJson(route('admin.long.audio.unsplittable', $audio))->assertStatus(422);

    expect($audio->refresh()->split_status)->toBe(AudioSplitStatusEnum::SPLIT);
});
