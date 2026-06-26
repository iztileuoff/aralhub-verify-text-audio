<?php

use App\Enums\RoleEnum;
use App\Models\Audio;
use App\Models\Text;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('scopes the profile counts to the requested date', function () {
    $user = User::factory()->create(['role' => RoleEnum::SPEAKER->value]);
    Sanctum::actingAs($user);

    Audio::factory()->create(['edit_speaker_id' => $user->id, 'speak_finished_at' => '2026-06-20 23:59:59']);
    Audio::factory()->create(['edit_speaker_id' => $user->id, 'speak_finished_at' => '2026-06-21 00:00:00']);

    Text::factory()->create(['edit_user_id' => $user->id, 'edit_finished_at' => '2026-06-20 08:00:00']);
    Text::factory()->create(['edit_user_id' => $user->id, 'edit_finished_at' => '2026-06-19 08:00:00']);

    $data = $this->getJson(route('admin.profile.show', ['date' => '2026-06-20']))->json('data');

    expect($data['today_finished_speak_texts_count'])->toBe(1)
        ->and($data['today_finished_edit_texts_count'])->toBe(1);
});

it('rejects an invalid date format', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('admin.profile.show', ['date' => 'not-a-date']))
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('date');
});
