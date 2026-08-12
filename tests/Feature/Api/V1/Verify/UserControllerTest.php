<?php

use App\Enums\RoleEnum;
use App\Models\Audio;
use App\Models\File;
use App\Models\Specialization;
use App\Models\Text;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->file = File::factory()->create();
    config(['dataset.main_file_id' => $this->file->id]);

    Sanctum::actingAs(User::factory()->create(['role' => RoleEnum::SUPER_ADMIN->value]));
});

it('counts only the work finished on the requested date', function () {
    $speaker = User::factory()->create(['role' => RoleEnum::SPEAKER->value]);
    $text = Text::factory()->main()->create();

    // Speak audio on the target day, including the inclusive day boundaries.
    Audio::factory()->create(['text_id' => $text->id, 'edit_speaker_id' => $speaker->id, 'speak_finished_at' => '2026-06-20 00:00:00']);
    Audio::factory()->create(['text_id' => $text->id, 'edit_speaker_id' => $speaker->id, 'speak_finished_at' => '2026-06-20 23:59:59']);

    // Adjacent days must be excluded.
    Audio::factory()->create(['text_id' => $text->id, 'edit_speaker_id' => $speaker->id, 'speak_finished_at' => '2026-06-19 23:59:59']);
    Audio::factory()->create(['text_id' => $text->id, 'edit_speaker_id' => $speaker->id, 'speak_finished_at' => '2026-06-21 00:00:00']);

    // Edited texts: one on the day, one outside it.
    Text::factory()->main()->create(['edit_user_id' => $speaker->id, 'edit_finished_at' => '2026-06-20 12:00:00']);
    Text::factory()->main()->create(['edit_user_id' => $speaker->id, 'edit_finished_at' => '2026-06-18 12:00:00']);

    $data = $this->getJson(route('admin.verify.users', ['date' => '2026-06-20']))->json('data');

    $row = collect($data)->firstWhere('id', $speaker->id);

    expect($row['today_finished_speak_texts_count'])->toBe(2)
        ->and($row['today_finished_edit_texts_count'])->toBe(1);
});

it('counts only work on the active dataset file', function () {
    $speaker = User::factory()->create(['role' => RoleEnum::SPEAKER->value]);

    $mainText = Text::factory()->main()->create();
    $otherText = Text::factory()->create();

    Audio::factory()->create(['text_id' => $mainText->id, 'edit_speaker_id' => $speaker->id, 'speak_finished_at' => '2026-06-20 10:00:00']);
    Audio::factory()->count(3)->create(['text_id' => $otherText->id, 'edit_speaker_id' => $speaker->id, 'speak_finished_at' => '2026-06-20 10:00:00']);

    Text::factory()->main()->create(['edit_speaker_id' => $speaker->id, 'speak_finished_at' => '2026-06-20 10:00:00']);
    Text::factory()->count(3)->create(['edit_speaker_id' => $speaker->id, 'speak_finished_at' => '2026-06-20 10:00:00']);

    $data = $this->getJson(route('admin.verify.users', ['date' => '2026-06-20']))->json('data');

    $row = collect($data)->firstWhere('id', $speaker->id);

    expect($row['today_finished_speak_texts_count'])->toBe(1)
        ->and($row['finished_speak_texts_count'])->toBe(1);
});

it('rejects an invalid date format', function () {
    $this->getJson(route('admin.verify.users', ['date' => '20-06-2026']))
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('date');
});

it('filters users by their specialization', function () {
    $wanted = Specialization::factory()->create();
    $other = Specialization::factory()->create();

    $matching = User::factory()->create(['role' => RoleEnum::SPEAKER->value, 'specialization_id' => $wanted->id]);
    User::factory()->create(['role' => RoleEnum::SPEAKER->value, 'specialization_id' => $other->id]);

    $ids = collect($this->getJson(route('admin.verify.users', ['specialization_id' => $wanted->id]))->json('data'))
        ->pluck('id');

    expect($ids->all())->toBe([$matching->id]);
});

it('rejects an unknown specialization_id', function () {
    $this->getJson(route('admin.verify.users', ['specialization_id' => 999999]))
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('specialization_id');
});

it('keeps the super admin excluded when searching by name', function () {
    $superAdmin = User::factory()->create(['role' => RoleEnum::SUPER_ADMIN->value, 'first_name' => 'Zenith']);
    $speaker = User::factory()->create(['role' => RoleEnum::SPEAKER->value, 'first_name' => 'Zenith']);

    $ids = collect($this->getJson(route('admin.verify.users', ['search' => 'Zenith']))->json('data'))
        ->pluck('id');

    expect($ids)->toContain($speaker->id)
        ->not->toContain($superAdmin->id);
});
