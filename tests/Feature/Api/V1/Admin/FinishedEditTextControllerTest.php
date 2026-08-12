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

    Sanctum::actingAs(User::factory()->create(['role' => RoleEnum::ADMIN->value]));
});

it('lists the most recently edited texts of the active dataset first', function () {
    $older = Text::factory()->main()->create(['edit_finished_at' => now()->subDays(3)]);
    $newer = Text::factory()->main()->create(['edit_finished_at' => now()->subDay()]);

    Text::factory()->main()->create(['edit_finished_at' => null]);

    $ids = $this->getJson(route('admin.finished.edit.texts'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$newer->id, $older->id]);
});

it('leaves out texts of other datasets by default', function () {
    $active = Text::factory()->main()->create(['edit_finished_at' => now()]);

    Text::factory()->create(['is_main' => true, 'edit_finished_at' => now()]);
    Text::factory()->create([
        'file_id' => $this->file->id,
        'is_split_part' => true,
        'edit_finished_at' => now(),
    ]);

    $ids = $this->getJson(route('admin.finished.edit.texts'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$active->id]);
});

it('reaches an older dataset when a file id is given', function () {
    $legacyFile = File::factory()->create();
    $legacy = Text::factory()->create([
        'file_id' => $legacyFile->id,
        'is_main' => true,
        'edit_finished_at' => now(),
    ]);

    Text::factory()->main()->create(['edit_finished_at' => now()]);

    $ids = $this->getJson(route('admin.finished.edit.texts', ['file_id' => $legacyFile->id]))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$legacy->id]);
});

it('filters by the editing user', function () {
    $editor = User::factory()->create(['role' => RoleEnum::EDITOR->value]);

    $target = Text::factory()->main()->create([
        'edit_user_id' => $editor->id,
        'edit_finished_at' => now(),
    ]);

    Text::factory()->main()->create(['edit_finished_at' => now()]);

    $ids = $this->getJson(route('admin.finished.edit.texts', ['edit_user_id' => $editor->id]))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$target->id]);
});
