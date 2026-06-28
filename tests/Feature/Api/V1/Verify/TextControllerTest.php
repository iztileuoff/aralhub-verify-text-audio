<?php

use App\Enums\RoleEnum;
use App\Models\Text;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create(['role' => RoleEnum::EDITOR->value]));
});

it('does not assign a split-part text for editing', function () {
    // Would qualify for the edit queue if it were a normal text.
    Text::factory()->create([
        'is_split_part' => true,
        'filter_original_transcript' => 'something to edit',
        'edit_user_id' => null,
    ]);

    $this->getJson(route('admin.verify.text'))->assertStatus(404);
});

it('assigns a normal text for editing', function () {
    $text = Text::factory()->create([
        'filter_original_transcript' => 'something to edit',
        'edit_user_id' => null,
    ]);

    $this->getJson(route('admin.verify.text'))
        ->assertOk()
        ->assertJsonPath('data.id', $text->id);
});
