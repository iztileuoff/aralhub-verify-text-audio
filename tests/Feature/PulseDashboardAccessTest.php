<?php

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

it('grants Pulse access to privileged roles', function (RoleEnum $role) {
    $user = User::factory()->create(['role' => $role->value]);

    expect(Gate::forUser($user)->allows('viewPulse'))->toBeTrue();
})->with([
    'super admin' => RoleEnum::SUPER_ADMIN,
    'admin' => RoleEnum::ADMIN,
]);

it('denies Pulse access to non-privileged roles', function (RoleEnum $role) {
    $user = User::factory()->create(['role' => $role->value]);

    expect(Gate::forUser($user)->allows('viewPulse'))->toBeFalse();
})->with([
    'editor' => RoleEnum::EDITOR,
    'speaker' => RoleEnum::SPEAKER,
    'moderator' => RoleEnum::MODERATOR,
]);

it('blocks unauthorized users from the Pulse dashboard route', function () {
    $user = User::factory()->create(['role' => RoleEnum::SPEAKER->value]);

    $this->actingAs($user)->get('/pulse')->assertForbidden();
});
