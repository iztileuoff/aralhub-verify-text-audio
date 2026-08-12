<?php

use App\Enums\RoleEnum;
use App\Models\File;
use App\Models\Text;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->file = File::factory()->create();
    config(['dataset.main_file_id' => $this->file->id]);

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

it('assigns only texts of the active dataset file', function () {
    $queued = [
        'speak_finished_at' => now(),
        'moderator_started_at' => null,
        'is_correct' => null,
    ];

    // Would qualify except that it belongs to a previous dataset.
    Text::factory()->create($queued);

    $target = Text::factory()->main()->create($queued);

    $id = $this->getJson(route('admin.verify.moderator.text'))->assertOk()->json('data.id');

    expect($id)->toBe($target->id);
});
