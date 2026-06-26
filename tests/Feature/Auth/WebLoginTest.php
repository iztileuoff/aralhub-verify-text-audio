<?php

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects guests from the dashboard to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('redirects guests from the Pulse dashboard to the login page', function () {
    $this->get('/pulse')->assertRedirect('/login');
});

it('redirects guests from the API documentation to the login page', function () {
    $this->get('/docs/api')->assertRedirect('/login');
});

it('renders the login screen for guests', function () {
    $this->get('/login')
        ->assertOk()
        ->assertViewIs('auth.login');
});

it('logs an admin in with valid credentials', function (RoleEnum $role) {
    $user = User::factory()->create([
        'role' => $role->value,
        'phone' => '998901234567',
        'password' => 'secret-password',
    ]);

    $this->post('/login', [
        'phone' => '998901234567',
        'password' => 'secret-password',
    ])->assertRedirect('/dashboard');

    expect(auth()->id())->toBe($user->id);
})->with([
    'super admin' => RoleEnum::SUPER_ADMIN,
    'admin' => RoleEnum::ADMIN,
]);

it('logs in regardless of the phone input format', function (string $input) {
    $user = User::factory()->admin()->create([
        'phone' => '998901234567',
        'password' => 'secret-password',
    ]);

    $this->post('/login', [
        'phone' => $input,
        'password' => 'secret-password',
    ])->assertRedirect('/dashboard');

    expect(auth()->id())->toBe($user->id);
})->with([
    'leading plus' => '+998901234567',
    'spaces and dashes' => '+998 90 123-45-67',
    'bare subscriber' => '901234567',
    'zero trunk prefix' => '0901234567',
]);

it('rejects login with an invalid password', function () {
    User::factory()->admin()->create([
        'phone' => '998901234567',
        'password' => 'secret-password',
    ]);

    $this->from('/login')->post('/login', [
        'phone' => '998901234567',
        'password' => 'wrong-password',
    ])->assertRedirect('/login')
        ->assertSessionHasErrors('phone');

    expect(auth()->check())->toBeFalse();
});

it('rejects login for an unknown phone', function () {
    $this->from('/login')->post('/login', [
        'phone' => '998901234567',
        'password' => 'secret-password',
    ])->assertSessionHasErrors('phone');

    expect(auth()->check())->toBeFalse();
});

it('forbids non-admin roles from logging into the panel', function (RoleEnum $role) {
    User::factory()->create([
        'role' => $role->value,
        'phone' => '998901234567',
        'password' => 'secret-password',
    ]);

    $this->from('/login')->post('/login', [
        'phone' => '998901234567',
        'password' => 'secret-password',
    ])->assertRedirect('/login')
        ->assertSessionHasErrors('phone');

    expect(auth()->check())->toBeFalse();
})->with([
    'editor' => RoleEnum::EDITOR,
    'speaker' => RoleEnum::SPEAKER,
    'moderator' => RoleEnum::MODERATOR,
]);

it('forbids inactive admins from logging in', function () {
    User::factory()->admin()->inactive()->create([
        'phone' => '998901234567',
        'password' => 'secret-password',
    ]);

    $this->from('/login')->post('/login', [
        'phone' => '998901234567',
        'password' => 'secret-password',
    ])->assertSessionHasErrors('phone');

    expect(auth()->check())->toBeFalse();
});

it('validates the phone format', function () {
    $this->from('/login')->post('/login', [
        'phone' => 'not-a-phone',
        'password' => 'secret-password',
    ])->assertSessionHasErrors('phone');

    expect(auth()->check())->toBeFalse();
});

it('lets an authenticated admin view the dashboard', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertViewIs('dashboard');
});

it('logs the user out', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect('/login');

    expect(auth()->check())->toBeFalse();
});
