<?php

use App\Enums\RoleEnum;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('indexes the sort of the texts list and the error texts list', function (array $columns) {
    expect(Schema::hasIndex('texts', $columns))->toBeTrue();
})->with([
    'admin texts list' => [['is_split_part', 'edit_finished_at']],
    'error texts list' => [['has_text_error', 'text_error_reported_at']],
]);

/**
 * Sign in with a real token whose last use was the given number of seconds ago.
 */
function tokenUsedSecondsAgo(int $seconds): array
{
    $user = User::factory()->create(['role' => RoleEnum::SPEAKER->value]);
    $plain = $user->createToken('test')->plainTextToken;
    $token = PersonalAccessToken::findToken($plain);
    $token->forceFill(['last_used_at' => now()->subSeconds($seconds)])->saveQuietly();

    return [$plain, $token->fresh()];
}

it('does not rewrite last_used_at on every request within a minute', function () {
    [$plain, $token] = tokenUsedSecondsAgo(20);
    $before = $token->last_used_at;

    $this->withToken($plain)->getJson(route('admin.profile.show'))->assertSuccessful();

    expect($token->fresh()->last_used_at->equalTo($before))->toBeTrue();
});

it('still records the last use once a minute has passed', function () {
    [$plain, $token] = tokenUsedSecondsAgo(120);

    $this->withToken($plain)->getJson(route('admin.profile.show'))->assertSuccessful();

    expect($token->fresh()->last_used_at->greaterThan(now()->subSeconds(5)))->toBeTrue();
});

it('records the first use of a fresh token', function () {
    $user = User::factory()->create(['role' => RoleEnum::SPEAKER->value]);
    $plain = $user->createToken('test')->plainTextToken;

    $this->withToken($plain)->getJson(route('admin.profile.show'))->assertSuccessful();

    expect(PersonalAccessToken::findToken($plain)->last_used_at)->not->toBeNull();
});

it('saves other token changes right away', function () {
    [, $token] = tokenUsedSecondsAgo(20);

    $token->forceFill(['name' => 'renamed', 'last_used_at' => now()])->save();

    expect($token->fresh()->name)->toBe('renamed');
});
