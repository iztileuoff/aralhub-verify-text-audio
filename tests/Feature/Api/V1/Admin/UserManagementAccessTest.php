<?php

use App\Enums\RoleEnum;
use App\Models\Specialization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function newUserPayload(array $overrides = []): array
{
    return array_merge([
        'role' => RoleEnum::SPEAKER->value,
        'first_name' => 'Aybek',
        'last_name' => 'Qudaybergenov',
        'phone' => '998'.fake()->unique()->numerify('#########'),
        'password' => 'secret-password',
        'gender' => 'MALE',
        'age' => 21,
        'specialization_id' => Specialization::factory()->create()->id,
        'course' => 2,
        'is_verified' => true,
    ], $overrides);
}

it('does not let a self-registered speaker promote themselves to admin', function () {
    $response = $this->postJson(route('admin.auth.registration'), newUserPayload())->assertSuccessful();

    $speaker = User::query()->findOrFail($response->json('data.user.id'));

    $this->withToken($response->json('data.access_token'))
        ->patchJson(route('admin.users.update', $speaker), ['role' => RoleEnum::ADMIN->value])
        ->assertForbidden();

    expect($speaker->fresh()->role)->toBe(RoleEnum::SPEAKER);
});

it('registers every newcomer as an unverified speaker whatever they send', function (int $role) {
    $response = $this->postJson(route('admin.auth.registration'), newUserPayload([
        'role' => $role,
        'is_verified' => true,
    ]))->assertSuccessful();

    $user = User::query()->findOrFail($response->json('data.user.id'));

    expect($user->role)->toBe(RoleEnum::SPEAKER)
        ->and($user->is_verified)->toBeFalse();
})->with([
    'super admin' => RoleEnum::SUPER_ADMIN->value,
    'admin' => RoleEnum::ADMIN->value,
    'moderator' => RoleEnum::MODERATOR->value,
]);

it('keeps worker roles away from user management', function (RoleEnum $role) {
    $victim = User::factory()->create(['role' => RoleEnum::SPEAKER->value]);
    Sanctum::actingAs(User::factory()->create(['role' => $role->value]));

    $this->getJson(route('admin.users.index'))->assertForbidden();
    $this->getJson(route('admin.users.show', $victim))->assertForbidden();
    $this->postJson(route('admin.users.store'), newUserPayload())->assertForbidden();
    $this->patchJson(route('admin.users.update', $victim), ['role' => RoleEnum::MODERATOR->value])->assertForbidden();
    $this->deleteJson(route('admin.users.destroy', $victim))->assertForbidden();

    expect($victim->fresh())->not->toBeNull()
        ->and($victim->fresh()->role)->toBe(RoleEnum::SPEAKER);
})->with([RoleEnum::EDITOR, RoleEnum::SPEAKER, RoleEnum::MODERATOR]);

it('lets an admin manage their own users', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['role' => RoleEnum::SPEAKER->value, 'admin_id' => $admin->id]);
    Sanctum::actingAs($admin);

    $this->getJson(route('admin.users.show', $user))->assertSuccessful();
    $this->patchJson(route('admin.users.update', $user), ['role' => RoleEnum::MODERATOR->value])->assertSuccessful();

    expect($user->fresh()->role)->toBe(RoleEnum::MODERATOR);

    $this->deleteJson(route('admin.users.destroy', $user))->assertSuccessful();

    expect($user->fresh())->toBeNull();
});

it('keeps an admin away from users that are not theirs', function (?string $owner) {
    $admin = User::factory()->admin()->create();
    $adminId = $owner === 'other admin' ? User::factory()->admin()->create()->id : null;
    $user = User::factory()->create(['role' => RoleEnum::SPEAKER->value, 'admin_id' => $adminId]);
    Sanctum::actingAs($admin);

    $this->getJson(route('admin.users.show', $user))->assertForbidden();
    $this->patchJson(route('admin.users.update', $user), ['role' => RoleEnum::MODERATOR->value])->assertForbidden();
    $this->deleteJson(route('admin.users.destroy', $user))->assertForbidden();

    expect($user->fresh())->not->toBeNull()
        ->and($user->fresh()->role)->toBe(RoleEnum::SPEAKER);
})->with(['other admin', 'nobody']);

it('keeps an admin away from the super admin', function () {
    $superAdmin = User::factory()->create(['role' => RoleEnum::SUPER_ADMIN->value]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->patchJson(route('admin.users.update', $superAdmin), ['role' => RoleEnum::SPEAKER->value])->assertForbidden();
    $this->deleteJson(route('admin.users.destroy', $superAdmin))->assertForbidden();

    expect($superAdmin->fresh()->role)->toBe(RoleEnum::SUPER_ADMIN);
});

it('assigns users created by an admin to that admin', function () {
    $admin = User::factory()->admin()->create();
    Sanctum::actingAs($admin);

    $response = $this->postJson(route('admin.users.store'), newUserPayload())->assertSuccessful();

    expect(User::query()->findOrFail($response->json('data.id'))->admin_id)->toBe($admin->id);

    $this->getJson(route('admin.users.index'))
        ->assertSuccessful()
        ->assertJsonFragment(['id' => $response->json('data.id')]);
});

it('does not let an admin hand a user over to another admin', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $user = User::factory()->create(['role' => RoleEnum::SPEAKER->value, 'admin_id' => $admin->id]);
    Sanctum::actingAs($admin);

    $this->patchJson(route('admin.users.update', $user), ['admin_id' => $otherAdmin->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('admin_id');

    expect($user->fresh()->admin_id)->toBe($admin->id);
});

it('does not let an admin grant the admin role', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['role' => RoleEnum::SPEAKER->value, 'admin_id' => $admin->id]);
    Sanctum::actingAs($admin);

    $this->patchJson(route('admin.users.update', $user), ['role' => RoleEnum::ADMIN->value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    $this->postJson(route('admin.users.store'), newUserPayload(['role' => RoleEnum::ADMIN->value]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    expect($user->fresh()->role)->toBe(RoleEnum::SPEAKER);
});

it('lets the super admin grant the admin role', function () {
    $user = User::factory()->create(['role' => RoleEnum::SPEAKER->value]);
    Sanctum::actingAs(User::factory()->create(['role' => RoleEnum::SUPER_ADMIN->value]));

    $this->patchJson(route('admin.users.update', $user), ['role' => RoleEnum::ADMIN->value])->assertSuccessful();

    expect($user->fresh()->role)->toBe(RoleEnum::ADMIN);
});

it('never grants the super admin role', function (RoleEnum $actor) {
    $user = User::factory()->create(['role' => RoleEnum::SPEAKER->value]);
    $acting = User::factory()->create(['role' => $actor->value]);
    $user->update(['admin_id' => $acting->id]);
    Sanctum::actingAs($acting);

    $this->patchJson(route('admin.users.update', $user), ['role' => RoleEnum::SUPER_ADMIN->value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    $this->postJson(route('admin.users.store'), newUserPayload(['role' => RoleEnum::SUPER_ADMIN->value]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    expect($user->fresh()->role)->toBe(RoleEnum::SPEAKER);
})->with([RoleEnum::SUPER_ADMIN, RoleEnum::ADMIN]);

it('offers each role only the roles it may grant', function (RoleEnum $actor, array $expected) {
    Sanctum::actingAs(User::factory()->create(['role' => $actor->value]));

    $ids = collect($this->getJson(route('admin.roles.index'))->assertSuccessful()->json('data'))->pluck('id')->all();

    expect($ids)->toBe($expected);
})->with([
    'super admin' => [RoleEnum::SUPER_ADMIN, [2, 3, 4, 5, 6]],
    'admin' => [RoleEnum::ADMIN, [3, 4, 5, 6]],
]);
