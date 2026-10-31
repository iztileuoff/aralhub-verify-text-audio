<?php

use App\Enums\RoleEnum;
use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['dataset.main_file_id' => File::factory()->create()->id]);
});

/**
 * Who may open which admin and verify route. Mirrors the role lists of the
 * admin panel menu (dataset.aralhub.uz): management for admins, the "Admin
 * paneli" (texts, dataset files, specializations) for the super admin only,
 * each work queue for its own role plus admins.
 *
 * @return array<string, array{0: string, 1: string, 2: array<int, RoleEnum>}>
 */
function routeAccessMatrix(): array
{
    $everyone = [RoleEnum::SUPER_ADMIN, RoleEnum::ADMIN, RoleEnum::EDITOR, RoleEnum::SPEAKER, RoleEnum::MODERATOR];
    $admins = [RoleEnum::SUPER_ADMIN, RoleEnum::ADMIN];
    $superAdmin = [RoleEnum::SUPER_ADMIN];

    return [
        'daily quota' => ['get', 'admin.daily.quota.texts.show', $everyone],
        'daily quota refresh' => ['put', 'admin.daily.quota.texts.update', $admins],
        'own profile report' => ['get', 'admin.profile.report', $everyone],

        'roles' => ['get', 'admin.roles.index', $admins],
        'users' => ['get', 'admin.users.index', $admins],
        'text statuses' => ['get', 'admin.text-statuses.index', $admins],
        'actions' => ['get', 'admin.actions.index', $admins],
        'finished edit texts' => ['get', 'admin.finished.edit.texts', $admins],
        'finished audio texts' => ['get', 'admin.finished.audio.texts', $admins],
        'finished check texts' => ['get', 'admin.finished.check.texts', $admins],
        'error texts' => ['get', 'admin.error.texts', $admins],
        'active dataset' => ['get', 'admin.dataset.active', $admins],
        'export report users' => ['get', 'admin.export.report-users', $admins],
        'report speakers' => ['get', 'admin.report.speakers', $admins],
        'report moderators' => ['get', 'admin.report.moderators', $admins],
        'finished audio' => ['get', 'admin.finished.audio', $admins],
        'long audio' => ['get', 'admin.long.audio', $admins],
        'long audio processed' => ['get', 'admin.long.audio.processed', $admins],
        'verify users' => ['get', 'admin.verify.users', $admins],

        'texts' => ['get', 'admin.texts.index', $superAdmin],
        'texts store' => ['post', 'admin.texts.store', $superAdmin],
        'files' => ['get', 'admin.files.index', $superAdmin],
        'files store' => ['post', 'admin.files.store', $superAdmin],
        'specializations' => ['get', 'admin.specializations.index', $superAdmin],
        'specializations store' => ['post', 'admin.specializations.store', $superAdmin],
        'dataset upload' => ['post', 'admin.dataset', $superAdmin],

        'edit queue' => ['get', 'admin.verify.text', [RoleEnum::SUPER_ADMIN, RoleEnum::ADMIN, RoleEnum::EDITOR]],
        'speak queue' => ['get', 'admin.verify.speak.text', [RoleEnum::SUPER_ADMIN, RoleEnum::ADMIN, RoleEnum::SPEAKER]],
        'moderator text queue' => ['get', 'admin.verify.moderator.text', [RoleEnum::SUPER_ADMIN, RoleEnum::ADMIN, RoleEnum::MODERATOR]],
        'moderator audio queue' => ['get', 'admin.verify.moderator.audio', [RoleEnum::SUPER_ADMIN, RoleEnum::ADMIN, RoleEnum::MODERATOR]],
    ];
}

/**
 * Every (route, role) pair, so a failure names exactly who got in or got locked out.
 *
 * @return array<string, array{0: string, 1: string, 2: RoleEnum, 3: bool}>
 */
function routeAccessCases(): array
{
    $cases = [];

    foreach (routeAccessMatrix() as $label => [$method, $route, $allowed]) {
        foreach ([RoleEnum::SUPER_ADMIN, RoleEnum::ADMIN, RoleEnum::EDITOR, RoleEnum::SPEAKER, RoleEnum::MODERATOR] as $role) {
            $isAllowed = in_array($role, $allowed, true);
            $cases["{$label} / {$role->getLabelText()} ".($isAllowed ? 'allowed' : 'denied')] = [$method, $route, $role, $isAllowed];
        }
    }

    return $cases;
}

it('opens each route to exactly the roles that work with it', function (string $method, string $route, RoleEnum $role, bool $allowed) {
    Sanctum::actingAs(User::factory()->create(['role' => $role->value]));

    /** @var TestResponse $response */
    $response = $this->{$method.'Json'}(route($route));

    if ($allowed) {
        expect($response->status())->not->toBeIn([401, 403])
            ->and($response->status())->toBeLessThan(500);
    } else {
        $response->assertForbidden();
    }
})->with(routeAccessCases());
