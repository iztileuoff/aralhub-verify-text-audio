<?php

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('calls');
});

it('keeps a trimmer out of every existing admin and verify section', function (string $route) {
    Sanctum::actingAs(User::factory()->trimmer()->create());

    $this->getJson(route($route))->assertForbidden();
})->with([
    'texts' => 'admin.texts.index',
    'users' => 'admin.users.index',
    'files' => 'admin.files.index',
    'reports' => 'admin.report.speakers',
    'long audio' => 'admin.long.audio',
    'speaker queue' => 'admin.verify.speak.text',
    'moderator queue' => 'admin.verify.moderator.audio',
]);

it('lets a trimmer read their own profile', function () {
    Sanctum::actingAs(User::factory()->trimmer()->create());

    $this->getJson(route('admin.profile.show'))->assertSuccessful();
});

it('keeps a trimmer out of moderation and statistics', function (string $route) {
    Sanctum::actingAs(User::factory()->trimmer()->create());

    $this->getJson(route($route))->assertForbidden();
})->with(['admin.calls.moderation', 'admin.calls.stats']);

it('opens the trimming queue to trimmers only', function (RoleEnum $role) {
    Sanctum::actingAs(User::factory()->create(['role' => $role->value]));

    $this->getJson(route('admin.calls.current'))->assertForbidden();
})->with([RoleEnum::SUPER_ADMIN, RoleEnum::ADMIN, RoleEnum::EDITOR, RoleEnum::SPEAKER, RoleEnum::MODERATOR]);

it('opens moderation and statistics to admins and super admins only', function (RoleEnum $role, bool $allowed) {
    Sanctum::actingAs(User::factory()->create(['role' => $role->value]));

    $response = $this->getJson(route('admin.calls.moderation'));

    $allowed ? $response->assertSuccessful() : $response->assertForbidden();
})->with([
    'super admin' => [RoleEnum::SUPER_ADMIN, true],
    'admin' => [RoleEnum::ADMIN, true],
    'editor' => [RoleEnum::EDITOR, false],
    'speaker' => [RoleEnum::SPEAKER, false],
    'moderator' => [RoleEnum::MODERATOR, false],
]);

it('still lets the other roles into the existing sections', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->getJson(route('admin.users.index'))->assertSuccessful();
});

it('lists the trimmer role so admins can create the accounts', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->getJson(route('admin.roles.index'))
        ->assertSuccessful()
        ->assertJsonFragment(['id' => RoleEnum::TRIMMER->value, 'name' => 'Trimmer']);
});

it('requires authentication', function () {
    $this->getJson(route('admin.calls.current'))->assertUnauthorized();
});
