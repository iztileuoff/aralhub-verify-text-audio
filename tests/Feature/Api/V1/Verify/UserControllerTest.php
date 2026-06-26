<?php

use App\Enums\RoleEnum;
use App\Models\Audio;
use App\Models\Text;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create(['role' => RoleEnum::SUPER_ADMIN->value]));
});

it('counts only the work finished on the requested date', function () {
    $speaker = User::factory()->create(['role' => RoleEnum::SPEAKER->value]);

    // Speak audio on the target day, including the inclusive day boundaries.
    Audio::factory()->create(['edit_speaker_id' => $speaker->id, 'speak_finished_at' => '2026-06-20 00:00:00']);
    Audio::factory()->create(['edit_speaker_id' => $speaker->id, 'speak_finished_at' => '2026-06-20 23:59:59']);

    // Adjacent days must be excluded.
    Audio::factory()->create(['edit_speaker_id' => $speaker->id, 'speak_finished_at' => '2026-06-19 23:59:59']);
    Audio::factory()->create(['edit_speaker_id' => $speaker->id, 'speak_finished_at' => '2026-06-21 00:00:00']);

    // Edited texts: one on the day, one outside it.
    Text::factory()->create(['edit_user_id' => $speaker->id, 'edit_finished_at' => '2026-06-20 12:00:00']);
    Text::factory()->create(['edit_user_id' => $speaker->id, 'edit_finished_at' => '2026-06-18 12:00:00']);

    $data = $this->getJson(route('admin.verify.users', ['date' => '2026-06-20']))->json('data');

    $row = collect($data)->firstWhere('id', $speaker->id);

    expect($row['today_finished_speak_texts_count'])->toBe(2)
        ->and($row['today_finished_edit_texts_count'])->toBe(1);
});

it('rejects an invalid date format', function () {
    $this->getJson(route('admin.verify.users', ['date' => '20-06-2026']))
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('date');
});
