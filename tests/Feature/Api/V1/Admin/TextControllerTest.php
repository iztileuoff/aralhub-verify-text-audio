<?php

use App\Enums\RoleEnum;
use App\Models\Text;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create(['role' => RoleEnum::ADMIN->value]));
});

it('excludes split-part texts from the admin text list', function () {
    $normal = Text::factory()->create();
    Text::factory()->create(['is_split_part' => true]);

    $ids = $this->getJson(route('admin.texts.index'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$normal->id]);
});
