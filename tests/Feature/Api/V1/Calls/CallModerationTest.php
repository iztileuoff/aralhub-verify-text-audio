<?php

use App\Enums\CallEventTypeEnum;
use App\Enums\CallStatusEnum;
use App\Models\CallRecording;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('calls');

    $this->admin = User::factory()->admin()->create();
    Sanctum::actingAs($this->admin);

    $this->trimmer = User::factory()->trimmer()->create();
});

it('lists submitted calls oldest first with both playback urls', function () {
    $older = CallRecording::factory()->submitted($this->trimmer)->create(['submitted_at' => now()->subHour()]);
    $newer = CallRecording::factory()->submitted($this->trimmer)->create();
    CallRecording::factory()->approved($this->trimmer)->create();
    CallRecording::factory()->inProgress($this->trimmer)->create();

    $response = $this->getJson(route('admin.calls.moderation'))
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $older->id)
        ->assertJsonPath('data.1.id', $newer->id)
        ->assertJsonPath('data.0.trimmer.id', $this->trimmer->id);

    expect($response->json('data.0.source_url'))->not->toBeNull()
        ->and($response->json('data.0.trimmed_url'))->toContain($older->trimmed_path);
});

it('shows admins the customer\'s original file name', function () {
    CallRecording::factory()->submitted($this->trimmer)->create([
        'source_path' => 'source/batch-1/call-000123.mp3',
        'original_filename' => 'Tulpor/2026-09-01/00-00-20_in_+998934880232_ZULXUMAR_X.mp3',
    ]);

    $this->getJson(route('admin.calls.moderation'))
        ->assertSuccessful()
        ->assertJsonPath('data.0.original_filename', 'Tulpor/2026-09-01/00-00-20_in_+998934880232_ZULXUMAR_X.mp3');
});

it('finds a call by its id, with or without #', function (string $search) {
    CallRecording::factory()->count(3)->submitted($this->trimmer)->create();
    $wanted = CallRecording::factory()->submitted($this->trimmer)->create(['id' => 704]);

    $this->getJson(route('admin.calls.moderation', ['search' => $search]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $wanted->id);
})->with(['704', '#704', ' # 704 ']);

it('searches the id within the chosen status, or everywhere with status=all', function () {
    CallRecording::factory()->approved($this->trimmer)->create(['id' => 704]);

    $this->getJson(route('admin.calls.moderation', ['search' => '704']))->assertJsonCount(0, 'data');

    $this->getJson(route('admin.calls.moderation', ['search' => '704', 'status' => 'all']))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'approved');
});

it('finds calls by a part of the neutral or the original file name', function () {
    CallRecording::factory()->submitted($this->trimmer)->create([
        'source_path' => 'source/batch-1/call-000123.mp3',
        'original_filename' => 'Tulpor/2026-09-04/11-56-51_in_+998973564734_MANSUR_J9.mp3',
    ]);
    CallRecording::factory()->submitted($this->trimmer)->create(['original_filename' => 'other.mp3']);

    $this->getJson(route('admin.calls.moderation', ['search' => 'call-000123']))->assertJsonCount(1, 'data');
    $this->getJson(route('admin.calls.moderation', ['search' => '998973564734']))->assertJsonCount(1, 'data');
    $this->getJson(route('admin.calls.moderation', ['search' => '%']))->assertJsonCount(0, 'data');
});

it('opens one call by id in any status', function () {
    $call = CallRecording::factory()->create();

    $this->getJson(route('admin.calls.show', $call))
        ->assertSuccessful()
        ->assertJsonPath('data.id', $call->id)
        ->assertJsonPath('data.status', 'pending');

    $this->getJson('/api/v1/admin/calls/999999')->assertNotFound();
});

it('does not open calls by id for trimmers', function () {
    $call = CallRecording::factory()->submitted($this->trimmer)->create();
    Sanctum::actingAs($this->trimmer);

    $this->getJson(route('admin.calls.show', $call))->assertForbidden();
    $this->getJson(route('admin.calls.current'))->assertSuccessful();
});

it('filters by status and trimmer', function () {
    $other = User::factory()->trimmer()->create();
    $skipped = CallRecording::factory()->skipped($this->trimmer)->create();
    CallRecording::factory()->skipped($other)->create();

    $this->getJson(route('admin.calls.moderation', ['status' => 'skipped', 'trimmer_id' => $this->trimmer->id]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $skipped->id);
});

it('approves a call and credits it to the trimmer', function () {
    $call = CallRecording::factory()->submitted($this->trimmer)->create();

    $this->postJson(route('admin.calls.approve', $call))
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.moderator.id', $this->admin->id);

    $call->refresh();

    expect($call->status)->toBe(CallStatusEnum::APPROVED)
        ->and($call->moderator_id)->toBe($this->admin->id)
        ->and($call->moderated_at)->not->toBeNull();

    $event = $call->events()->sole();

    expect($event->type)->toBe(CallEventTypeEnum::APPROVED)
        ->and($event->user_id)->toBe($this->admin->id)
        ->and($event->trimmer_id)->toBe($this->trimmer->id)
        ->and($event->trimmed_duration)->toBe($call->trimmed_duration);
});

it('lets the moderator fix the transcript while approving', function () {
    $call = CallRecording::factory()->submitted($this->trimmer)->create();

    $this->postJson(route('admin.calls.approve', $call), ['transcript' => 'Qayırlı kún, Íslam!'])->assertSuccessful();

    $call->refresh();

    expect($call->transcript)->toBe('Qayırlı kún, Íslam!')
        ->and($call->normalized_transcript)->toBe('qayırlı kún, ıslam')
        ->and($call->events()->sole()->comment)->toBe('transcript edited');
});

it('checks a corrected transcript with the same rules', function () {
    $call = CallRecording::factory()->submitted($this->trimmer)->create();

    $this->postJson(route('admin.calls.approve', $call), ['transcript' => 'Сәлем'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('transcript');

    expect($call->refresh()->status)->toBe(CallStatusEnum::SUBMITTED);
});

it('sends a call back to the same trimmer with the reason', function () {
    $call = CallRecording::factory()->submitted($this->trimmer)->create();

    $this->postJson(route('admin.calls.reject', $call), ['reason' => 'Aqırındaǵı tınıshlıq kesilmegen.'])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.reject_reason', 'Aqırındaǵı tınıshlıq kesilmegen.')
        ->assertJsonPath('data.rejections_count', 1);

    $call->refresh();

    expect($call->trimmer_id)->toBe($this->trimmer->id)
        ->and($call->events()->sole()->type)->toBe(CallEventTypeEnum::REJECTED);

    Sanctum::actingAs($this->trimmer);

    $this->getJson(route('admin.calls.current'))->assertJsonPath('data.id', $call->id);
});

it('needs a reason to send a call back', function () {
    $call = CallRecording::factory()->submitted($this->trimmer)->create();

    $this->postJson(route('admin.calls.reject', $call))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason' => 'Напишите, что нужно исправить.']);
});

it('decides only calls that wait for moderation', function (string $route, CallRecording $call) {
    $this->postJson(route($route, $call), ['reason' => 'x'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Звонок не ждёт модерации.');

    expect($call->events()->count())->toBe(0);
})->with([
    'approve approved' => ['admin.calls.approve', fn () => CallRecording::factory()->approved()->create()],
    'approve in progress' => ['admin.calls.approve', fn () => CallRecording::factory()->inProgress()->create()],
    'reject rejected' => ['admin.calls.reject', fn () => CallRecording::factory()->rejected()->create()],
    'reject pending' => ['admin.calls.reject', fn () => CallRecording::factory()->create()],
]);

it('puts an unusable call back into the queue', function () {
    $call = CallRecording::factory()->skipped($this->trimmer)->create(['skip_comment' => 'x']);

    $this->postJson(route('admin.calls.restore', $call))
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.trimmer', null)
        ->assertJsonPath('data.skip_reason', null);

    $event = $call->events()->sole();

    expect($event->type)->toBe(CallEventTypeEnum::RESTORED)
        ->and($event->trimmer_id)->toBe($this->trimmer->id)
        ->and($event->comment)->toBe('no_speech');
});

it('restores only unusable calls', function () {
    $call = CallRecording::factory()->submitted($this->trimmer)->create();

    $this->postJson(route('admin.calls.restore', $call))->assertUnprocessable();

    expect($call->refresh()->status)->toBe(CallStatusEnum::SUBMITTED);
});
