<?php

use App\Enums\RoleEnum;
use App\Models\Audio;
use App\Models\File;
use App\Models\Text;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    $this->file = File::factory()->create();
    config(['dataset.main_file_id' => $this->file->id]);

    Sanctum::actingAs(User::factory()->create(['role' => RoleEnum::ADMIN->value]));
});

it('returns the daily quota payload with the full set of keys', function () {
    $this->getJson(route('admin.daily.quota.texts.show'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'speakers_count',
                'users_count',
                'active_editors_count',
                'active_speaker_count',
                'active_moderators_count',
                'texts_count',
                'error_texts_count',
                'edit_finished_texts_count',
                'edit_not_finished_texts_count',
                'daily_quota_texts_count',
                'audio_finished_texts_count',
                'audio_not_finished_texts_count',
                'daily_quota_audios_count',
                'moderator_finished_audios_count',
                'moderator_not_finished_audios_count',
                'daily_quota_check_audios_count',
                'edit_finished_today_count',
                'speak_finished_today_count',
                'moderator_finished_today_count',
                'total_audios_count',
                'total_audio_duration_seconds',
                'total_audio_duration_hours',
                'error_texts_percent',
                'audio_progress_percent',
                'generated_at',
            ],
        ]);
});

it('counts speakers, users and active staff by role', function () {
    User::factory()->create(['role' => RoleEnum::SPEAKER->value, 'is_active' => true]);
    User::factory()->create(['role' => RoleEnum::SPEAKER->value, 'is_active' => false]);
    User::factory()->create(['role' => RoleEnum::EDITOR->value, 'is_active' => true]);
    User::factory()->create(['role' => RoleEnum::MODERATOR->value, 'is_active' => true]);

    $data = $this->getJson(route('admin.daily.quota.texts.show'))->json('data');

    expect($data['speakers_count'])->toBe(2)
        ->and($data['users_count'])->toBe(4)
        ->and($data['active_editors_count'])->toBe(1)
        ->and($data['active_speaker_count'])->toBe(1)
        ->and($data['active_moderators_count'])->toBe(1);
});

it('scopes text and error counts to the main dataset file', function () {
    Text::factory()->count(3)->main()->create();
    Text::factory()->main()->create(['has_text_error' => true]);

    // Noise that must be ignored: a text on another file and a non-main text on the same file.
    Text::factory()->create();
    Text::factory()->create(['file_id' => $this->file->id, 'is_main' => false]);

    $data = $this->getJson(route('admin.daily.quota.texts.show'))->json('data');

    expect($data['texts_count'])->toBe(4)
        ->and($data['error_texts_count'])->toBe(1)
        ->and((float) $data['error_texts_percent'])->toBe(25.0);
});

it('groups the moderation-check quota condition so unspoken texts are excluded', function () {
    // Spoken, awaiting moderation -> counted.
    Text::factory()->main()->create([
        'speak_finished_at' => now()->subDay(),
        'moderator_finished_at' => null,
    ]);

    // Spoken, moderated today -> counted.
    Text::factory()->main()->create([
        'speak_finished_at' => now()->subDay(),
        'moderator_finished_at' => now(),
    ]);

    // Spoken, moderated long ago -> excluded.
    Text::factory()->main()->create([
        'speak_finished_at' => now()->subDay(),
        'moderator_finished_at' => now()->subWeek(),
    ]);

    // Not spoken yet but flagged as moderated today: the previous ungrouped
    // orWhere wrongly counted this row; the fix must exclude it.
    Text::factory()->main()->create([
        'speak_finished_at' => null,
        'moderator_finished_at' => now(),
    ]);

    $data = $this->getJson(route('admin.daily.quota.texts.show'))->json('data');

    expect($data['daily_quota_check_audios_count'])->toBe(2);
});

it('aggregates audio totals, duration and today metrics for the main file', function () {
    $mainText = Text::factory()->main()->create();
    $otherText = Text::factory()->create();

    Audio::factory()->create([
        'text_id' => $mainText->id,
        'edit_converted_audio_duration' => 3600,
        'speak_finished_at' => now(),
        'moderator_finished_at' => now(),
        'is_correct' => true,
    ]);

    Audio::factory()->create([
        'text_id' => $mainText->id,
        'edit_converted_audio_duration' => 1800,
        'speak_finished_at' => now()->subWeek(),
        'moderator_finished_at' => null,
        'is_correct' => null,
    ]);

    // Audio on a non-main text is excluded from the totals.
    Audio::factory()->create([
        'text_id' => $otherText->id,
        'edit_converted_audio_duration' => 9999,
    ]);

    $data = $this->getJson(route('admin.daily.quota.texts.show'))->json('data');

    expect($data['total_audios_count'])->toBe(2)
        ->and($data['total_audio_duration_seconds'])->toBe(5400)
        ->and((float) $data['total_audio_duration_hours'])->toBe(1.5)
        ->and($data['speak_finished_today_count'])->toBe(1)
        ->and($data['moderator_finished_today_count'])->toBe(1)
        ->and($data['moderator_finished_audios_count'])->toBe(1)
        ->and($data['moderator_not_finished_audios_count'])->toBe(1);
});

it('aggregates edit progress and daily-quota text counts', function () {
    Text::factory()->main()->create(['edit_finished_at' => now(), 'audio_count' => 1]);
    Text::factory()->main()->create(['edit_finished_at' => now()->subWeek(), 'audio_count' => 0]);
    Text::factory()->main()->create(['edit_finished_at' => null, 'audio_count' => null]);

    $data = $this->getJson(route('admin.daily.quota.texts.show'))->json('data');

    expect($data['texts_count'])->toBe(3)
        ->and($data['edit_finished_texts_count'])->toBe(2)
        ->and($data['edit_not_finished_texts_count'])->toBe(1)
        ->and($data['edit_finished_today_count'])->toBe(1)
        ->and($data['audio_finished_texts_count'])->toBe(1)
        ->and($data['audio_not_finished_texts_count'])->toBe(2)
        ->and((float) $data['audio_progress_percent'])->toBe(33.33)
        ->and($data['daily_quota_texts_count'])->toBe(2)
        ->and($data['daily_quota_audios_count'])->toBe(3);
});

it('scopes the edit, quota and today counters to the active dataset file', function () {
    $mainText = Text::factory()->main()->create([
        'edit_finished_at' => now(),
        'speak_finished_at' => now(),
        'moderator_finished_at' => now(),
    ]);

    // A whole parallel dataset: none of its rows may reach any counter.
    $otherText = Text::factory()->create([
        'edit_finished_at' => now(),
        'speak_finished_at' => now(),
        'moderator_finished_at' => now(),
    ]);

    Text::factory()->count(3)->create([
        'file_id' => $otherText->file_id,
        'is_main' => true,
        'edit_finished_at' => null,
    ]);

    Audio::factory()->create([
        'text_id' => $mainText->id,
        'speak_finished_at' => now(),
        'moderator_finished_at' => now(),
    ]);

    Audio::factory()->count(4)->create([
        'text_id' => $otherText->id,
        'speak_finished_at' => now(),
        'moderator_finished_at' => now(),
    ]);

    $data = $this->getJson(route('admin.daily.quota.texts.show'))->json('data');

    expect($data['texts_count'])->toBe(1)
        ->and($data['edit_finished_texts_count'])->toBe(1)
        ->and($data['edit_not_finished_texts_count'])->toBe(0)
        ->and($data['edit_finished_today_count'])->toBe(1)
        ->and($data['daily_quota_texts_count'])->toBe(1)
        ->and($data['daily_quota_audios_count'])->toBe(1)
        ->and($data['daily_quota_check_audios_count'])->toBe(1)
        ->and($data['speak_finished_today_count'])->toBe(1)
        ->and($data['moderator_finished_today_count'])->toBe(1);
});

it('excludes split-part texts from the quota counters', function () {
    Text::factory()->main()->create(['edit_finished_at' => null, 'audio_count' => null]);

    // Child text produced by an audio split: must not inflate any text counter.
    Text::factory()->create([
        'is_split_part' => true,
        'edit_finished_at' => null,
        'speak_finished_at' => null,
    ]);

    $data = $this->getJson(route('admin.daily.quota.texts.show'))->json('data');

    expect($data['edit_not_finished_texts_count'])->toBe(1)
        ->and($data['daily_quota_texts_count'])->toBe(1)
        ->and($data['daily_quota_audios_count'])->toBe(1);
});

it('rebuilds the cached quota only when refreshed via update', function () {
    Text::factory()->main()->create();

    expect($this->getJson(route('admin.daily.quota.texts.show'))->json('data.texts_count'))->toBe(1);

    // New data must not appear until the cache is busted.
    Text::factory()->count(2)->main()->create();
    expect($this->getJson(route('admin.daily.quota.texts.show'))->json('data.texts_count'))->toBe(1);

    $refreshed = $this->putJson(route('admin.daily.quota.texts.update'))->json('data.texts_count');
    expect($refreshed)->toBe(3);
});
