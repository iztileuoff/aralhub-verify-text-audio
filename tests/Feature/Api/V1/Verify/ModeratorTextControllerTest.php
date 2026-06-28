<?php

use App\Enums\RoleEnum;
use App\Models\Text;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create(['role' => RoleEnum::MODERATOR->value]));
});

it('does not assign a split-part text for moderation', function () {
    // Would qualify for the moderation queue if it were a normal text.
    Text::factory()->create([
        'is_split_part' => true,
        'speak_finished_at' => now(),
        'moderator_started_at' => null,
        'is_correct' => null,
    ]);

    $this->getJson(route('admin.verify.moderator.text'))->assertStatus(404);
});
