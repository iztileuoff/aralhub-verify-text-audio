<?php

use App\Enums\RoleEnum;
use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['dataset.main_file_id' => File::factory()->create()->id]);
});

/**
 * The two xlsx reports carry full names and phone numbers of every user.
 *
 * @return array<string, array{0: string, 1: array<string, string>}>
 */
function guestExportRoutes(): array
{
    return [
        'report users' => ['/api/v1/guest/export/report-users', ['from_date' => '2026-10-01', 'to_date' => '2026-10-02']],
        'report all users' => ['/api/v1/guest/export/report-all-users', ['date' => '2026-10-01']],
    ];
}

it('refuses the users report to anonymous visitors', function (string $url, array $query) {
    $this->getJson($url.'?'.http_build_query($query))->assertUnauthorized();
})->with(guestExportRoutes());

it('refuses the users report to worker roles', function (RoleEnum $role, string $url, array $query) {
    Sanctum::actingAs(User::factory()->create(['role' => $role->value]));

    $this->getJson($url.'?'.http_build_query($query))->assertForbidden();
})->with([RoleEnum::EDITOR, RoleEnum::SPEAKER, RoleEnum::MODERATOR, RoleEnum::TRIMMER])->with(guestExportRoutes());

it('still gives the users report to admins', function (RoleEnum $role, string $url, array $query) {
    Sanctum::actingAs(User::factory()->create(['role' => $role->value]));

    $this->get($url.'?'.http_build_query($query))->assertSuccessful();
})->with([RoleEnum::SUPER_ADMIN, RoleEnum::ADMIN])->with(guestExportRoutes());

it('serves the users report from the admin api by GET as well', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->get(route('admin.export.report-users', ['from_date' => '2026-10-01', 'to_date' => '2026-10-02']))
        ->assertSuccessful();
});
