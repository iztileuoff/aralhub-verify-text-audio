<?php

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

it('grants API docs access to privileged roles', function (RoleEnum $role) {
    $user = User::factory()->create(['role' => $role->value]);

    expect(Gate::forUser($user)->allows('viewApiDocs'))->toBeTrue();
})->with([
    'super admin' => RoleEnum::SUPER_ADMIN,
    'admin' => RoleEnum::ADMIN,
]);

it('denies API docs access to non-privileged roles', function (RoleEnum $role) {
    $user = User::factory()->create(['role' => $role->value]);

    expect(Gate::forUser($user)->allows('viewApiDocs'))->toBeFalse();
})->with([
    'editor' => RoleEnum::EDITOR,
    'speaker' => RoleEnum::SPEAKER,
    'moderator' => RoleEnum::MODERATOR,
]);

it('blocks unauthorized users from the API documentation', function () {
    $this->actingAs(User::factory()->create(['role' => RoleEnum::SPEAKER->value]))
        ->get('/docs/api.json')
        ->assertForbidden();
});

it('lets admins load the generated API documentation', function () {
    $this->actingAs(User::factory()->create(['role' => RoleEnum::ADMIN->value]))
        ->get('/docs/api.json')
        ->assertSuccessful()
        ->assertJsonPath('info.title', 'Aralhub Verify API');
});
