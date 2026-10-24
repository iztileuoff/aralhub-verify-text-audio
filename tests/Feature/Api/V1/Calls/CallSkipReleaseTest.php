<?php

use App\Enums\CallEventTypeEnum;
use App\Enums\CallSkipReasonEnum;
use App\Enums\CallStatusEnum;
use App\Models\CallRecording;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('calls');

    $this->trimmer = User::factory()->trimmer()->create();
    Sanctum::actingAs($this->trimmer);
});

it('marks a call as unusable with a reason', function () {
    $call = CallRecording::factory()->inProgress($this->trimmer)->create();

    $this->postJson(route('admin.calls.skip', $call), ['reason' => 'no_speech'])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'skipped')
        ->assertJsonPath('data.skip_reason', 'no_speech');

    $call->refresh();

    expect($call->status)->toBe(CallStatusEnum::SKIPPED)
        ->and($call->skip_reason)->toBe(CallSkipReasonEnum::NO_SPEECH)
        ->and($call->trimmer_id)->toBe($this->trimmer->id)
        ->and($call->events()->sole()->type)->toBe(CallEventTypeEnum::SKIPPED);
});

it('needs a comment for the "other" reason', function () {
    $call = CallRecording::factory()->inProgress($this->trimmer)->create();

    $this->postJson(route('admin.calls.skip', $call), ['reason' => 'other'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('comment');

    $this->postJson(route('admin.calls.skip', $call), ['reason' => 'other', 'comment' => 'Avtojuwapshı'])
        ->assertSuccessful()
        ->assertJsonPath('data.skip_comment', 'Avtojuwapshı');
});

it('refuses an unknown skip reason', function () {
    $call = CallRecording::factory()->inProgress($this->trimmer)->create();

    $this->postJson(route('admin.calls.skip', $call), ['reason' => 'boring'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');
});

it('cannot skip a call held by someone else', function () {
    $call = CallRecording::factory()->inProgress(User::factory()->trimmer()->create())->create();

    $this->postJson(route('admin.calls.skip', $call), ['reason' => 'broken'])->assertConflict();

    expect($call->refresh()->status)->toBe(CallStatusEnum::IN_PROGRESS);
});

it('puts a call back in the queue for anyone', function () {
    $call = CallRecording::factory()->inProgress($this->trimmer)->create();

    $this->postJson(route('admin.calls.release', $call))
        ->assertSuccessful()
        ->assertJsonPath('message', 'Звонок возвращён в очередь.');

    $call->refresh();

    expect($call->status)->toBe(CallStatusEnum::PENDING)
        ->and($call->trimmer_id)->toBeNull()
        ->and($call->assigned_at)->toBeNull();

    $event = $call->events()->sole();

    expect($event->type)->toBe(CallEventTypeEnum::RELEASED)
        ->and($event->trimmer_id)->toBe($this->trimmer->id);
});

it('does not let a rejected call be released instead of fixed', function () {
    $call = CallRecording::factory()->rejected($this->trimmer)->create();
    $this->getJson(route('admin.calls.current'))->assertJsonPath('data.id', $call->id);

    $this->postJson(route('admin.calls.release', $call))->assertUnprocessable();

    expect($call->refresh()->status)->toBe(CallStatusEnum::IN_PROGRESS)
        ->and($call->trimmer_id)->toBe($this->trimmer->id);
});

it('cannot release a call held by someone else', function () {
    $other = User::factory()->trimmer()->create();
    $call = CallRecording::factory()->inProgress($other)->create();

    $this->postJson(route('admin.calls.release', $call))->assertConflict();

    expect($call->refresh()->trimmer_id)->toBe($other->id);
});

it('lists the trimmer\'s own finished work only', function () {
    $mine = CallRecording::factory()->submitted($this->trimmer)->create();
    CallRecording::factory()->approved($this->trimmer)->create();
    CallRecording::factory()->inProgress($this->trimmer)->create();
    CallRecording::factory()->submitted(User::factory()->trimmer()->create())->create();

    $this->getJson(route('admin.calls.my'))->assertSuccessful()->assertJsonCount(2, 'data');

    $this->getJson(route('admin.calls.my', ['status' => 'submitted']))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);
});
