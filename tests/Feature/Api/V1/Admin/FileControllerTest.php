<?php

use App\Enums\RoleEnum;
use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create(['role' => RoleEnum::ADMIN->value]));
});

it('marks only the configured dataset as active in the file list', function () {
    $legacy = File::factory()->create(['label' => 'v2']);
    $active = File::factory()->create(['label' => 'v3']);

    config(['dataset.main_file_id' => $active->id]);

    $data = $this->getJson(route('admin.files.index'))
        ->assertOk()
        ->json('data');

    $byId = collect($data)->keyBy('id');

    expect($byId[$active->id]['is_active'])->toBeTrue()
        ->and($byId[$active->id]['label'])->toBe('v3')
        ->and($byId[$legacy->id]['is_active'])->toBeFalse()
        ->and($byId[$legacy->id]['label'])->toBe('v2');
});

it('renames a dataset', function () {
    $file = File::factory()->create(['label' => null]);

    $this->patchJson(route('admin.files.update', $file), ['label' => 'v3'])
        ->assertOk()
        ->assertJsonPath('data.label', 'v3');

    expect($file->refresh()->label)->toBe('v3');
});

it('clears the label when null is sent', function () {
    $file = File::factory()->create(['label' => 'v3']);

    $this->patchJson(route('admin.files.update', $file), ['label' => null])
        ->assertOk()
        ->assertJsonPath('data.label', null);

    expect($file->refresh()->label)->toBeNull();
});

it('rejects a label longer than the column', function () {
    $file = File::factory()->create(['label' => 'v3']);

    $this->patchJson(route('admin.files.update', $file), ['label' => str_repeat('a', 256)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('label');

    expect($file->refresh()->label)->toBe('v3');
});

it('returns the active dataset without paging through the file list', function () {
    File::factory()->create(['label' => 'v2']);
    $active = File::factory()->create(['label' => 'v3']);

    config(['dataset.main_file_id' => $active->id]);

    $this->getJson(route('admin.dataset.active'))
        ->assertOk()
        ->assertJsonPath('data.id', $active->id)
        ->assertJsonPath('data.label', 'v3')
        ->assertJsonPath('data.is_active', true);
});

it('says so when the configured dataset does not exist', function () {
    config(['dataset.main_file_id' => 999]);

    $this->getJson(route('admin.dataset.active'))
        ->assertNotFound()
        ->assertJsonPath('message', 'Active dataset (file #999) does not exist.');
});
