<?php

use App\Enums\CallEventTypeEnum;
use App\Models\CallRecording;
use App\Models\CallRecordingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('calls');

    $this->trimmer = User::factory()->trimmer()->create(['first_name' => 'Aygul']);
});

/**
 * Log an event dated at the given moment.
 */
function callEvent(User $trimmer, CallEventTypeEnum $type, int $seconds, string $at, ?User $actor = null): void
{
    CallRecordingEvent::factory()->create([
        'user_id' => ($actor ?? $trimmer)->id,
        'trimmer_id' => $trimmer->id,
        'type' => $type,
        'trimmed_duration' => $seconds * 16000,
        'created_at' => $at,
        'updated_at' => $at,
    ]);
}

it('shows the state of the whole queue', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    CallRecording::factory()->count(3)->create();
    CallRecording::factory()->inProgress($this->trimmer)->create();
    CallRecording::factory()->submitted($this->trimmer)->create();
    CallRecording::factory()->approved($this->trimmer)->create(['trimmed_duration' => 60 * 16000]);
    CallRecording::factory()->approved($this->trimmer)->create(['trimmed_duration' => 30 * 16000, 'exported_at' => now()]);
    CallRecording::factory()->skipped($this->trimmer)->create();

    $this->getJson(route('admin.calls.stats'))
        ->assertSuccessful()
        ->assertJsonPath('data.totals', [
            'files_total' => 8,
            'pending' => 3,
            'in_progress' => 1,
            'submitted' => 1,
            'approved' => 2,
            'rejected' => 0,
            'skipped' => 1,
            'approved_duration_seconds' => 90,
            'exported' => 1,
        ]);
});

it('counts each trimmer\'s work in the period, crediting moderation to the trimmer', function () {
    $admin = User::factory()->admin()->create();
    Sanctum::actingAs($admin);

    callEvent($this->trimmer, CallEventTypeEnum::SUBMITTED, 60, '2026-10-10 09:00:00');
    callEvent($this->trimmer, CallEventTypeEnum::SUBMITTED, 40, '2026-10-11 09:00:00');
    callEvent($this->trimmer, CallEventTypeEnum::SKIPPED, 0, '2026-10-11 10:00:00');
    callEvent($this->trimmer, CallEventTypeEnum::APPROVED, 60, '2026-10-11 12:00:00', $admin);
    callEvent($this->trimmer, CallEventTypeEnum::REJECTED, 40, '2026-10-11 12:05:00', $admin);
    callEvent($this->trimmer, CallEventTypeEnum::SUBMITTED, 45, '2026-10-12 09:00:00');

    $idle = User::factory()->trimmer()->create(['first_name' => 'Zarina']);

    $this->getJson(route('admin.calls.stats', ['from' => '2026-10-10', 'to' => '2026-10-11']))
        ->assertSuccessful()
        ->assertJsonPath('data.period', ['from' => '2026-10-10', 'to' => '2026-10-11'])
        ->assertJsonPath('data.trimmers.0.user.id', $this->trimmer->id)
        ->assertJsonPath('data.trimmers.0.submitted', 2)
        ->assertJsonPath('data.trimmers.0.approved', 1)
        ->assertJsonPath('data.trimmers.0.rejected', 1)
        ->assertJsonPath('data.trimmers.0.skipped', 1)
        ->assertJsonPath('data.trimmers.0.trimmed_duration_seconds', 100)
        ->assertJsonPath('data.trimmers.0.approved_duration_seconds', 60)
        ->assertJsonPath('data.trimmers.0.last_activity_at', '2026-10-12T09:00:00+00:00')
        ->assertJsonPath('data.trimmers.1.user.id', $idle->id)
        ->assertJsonPath('data.trimmers.1.submitted', 0)
        ->assertJsonPath('data.trimmers.1.last_activity_at', null);
});

it('defaults the period to today', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    callEvent($this->trimmer, CallEventTypeEnum::SUBMITTED, 60, now()->toDateTimeString());
    callEvent($this->trimmer, CallEventTypeEnum::SUBMITTED, 60, now()->subDays(2)->toDateTimeString());

    $this->getJson(route('admin.calls.stats'))
        ->assertJsonPath('data.period.from', today()->toDateString())
        ->assertJsonPath('data.trimmers.0.submitted', 1);
});

it('validates the period', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->getJson(route('admin.calls.stats', ['from' => '2026-10-11', 'to' => '2026-10-10']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');
});

it('shows a trimmer their own day and totals', function () {
    Sanctum::actingAs($this->trimmer);
    $admin = User::factory()->admin()->create();

    callEvent($this->trimmer, CallEventTypeEnum::SUBMITTED, 60, '2026-10-11 09:00:00');
    callEvent($this->trimmer, CallEventTypeEnum::SUBMITTED, 30, '2026-10-11 09:30:00');
    callEvent($this->trimmer, CallEventTypeEnum::APPROVED, 60, '2026-10-11 12:00:00', $admin);
    callEvent($this->trimmer, CallEventTypeEnum::SUBMITTED, 45, '2026-10-09 09:00:00');
    callEvent($this->trimmer, CallEventTypeEnum::APPROVED, 45, '2026-10-09 12:00:00', $admin);
    callEvent(User::factory()->trimmer()->create(), CallEventTypeEnum::SUBMITTED, 99, '2026-10-11 09:00:00');
    CallRecording::factory()->submitted($this->trimmer)->create();

    $this->getJson(route('admin.calls.my.stats', ['date' => '2026-10-11']))
        ->assertSuccessful()
        ->assertJsonPath('data', [
            'date' => '2026-10-11',
            'day' => [
                'submitted' => 2,
                'approved' => 1,
                'rejected' => 0,
                'skipped' => 0,
                'trimmed_duration_seconds' => 90,
            ],
            'total' => [
                'submitted' => 3,
                'approved' => 2,
                'rejected' => 0,
                'skipped' => 0,
                'on_moderation' => 1,
                'approved_duration_seconds' => 105,
            ],
        ]);
});
