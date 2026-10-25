<?php

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

it('books the oldest free call for the trimmer', function () {
    $first = CallRecording::factory()->create();
    CallRecording::factory()->create();

    $this->getJson(route('admin.calls.current'))
        ->assertSuccessful()
        ->assertJsonPath('data.id', $first->id)
        ->assertJsonPath('data.status', 'in_progress')
        ->assertJsonPath('data.trimmer.id', $this->trimmer->id)
        ->assertJsonPath('data.original_filename', $first->original_filename);

    $first->refresh();

    expect($first->status)->toBe(CallStatusEnum::IN_PROGRESS)
        ->and($first->trimmer_id)->toBe($this->trimmer->id)
        ->and($first->assigned_at)->not->toBeNull();
});

it('shows the trimmer the neutral file name, never the customer\'s one', function () {
    CallRecording::factory()->create([
        'source_path' => 'source/batch-1/call-000123.mp3',
        'original_filename' => 'Tulpor/2026-09-01/00-00-20_in_+998934880232_ZULXUMAR_X.mp3',
    ]);

    $response = $this->getJson(route('admin.calls.current'))
        ->assertSuccessful()
        ->assertJsonPath('data.original_filename', 'call-000123.mp3');

    expect($response->getContent())->not->toContain('998934880232')->not->toContain('ZULXUMAR');
});

it('hands out a signed, expiring url instead of a public one', function () {
    CallRecording::factory()->create(['source_path' => 'source/b1/x.mp3']);

    $response = $this->getJson(route('admin.calls.current'))->assertSuccessful();

    expect($response->json('data.source_url'))->toContain('source/b1/x.mp3')->toContain('expiration=')
        ->and($response->json('data.source_url_expires_at'))->not->toBeNull()
        ->and($response->json('data.trimmed_url'))->toBeNull();
});

it('gives the same call back on reload instead of booking another', function () {
    $mine = CallRecording::factory()->inProgress($this->trimmer)->create();
    CallRecording::factory()->create();

    $this->getJson(route('admin.calls.current'))->assertJsonPath('data.id', $mine->id);
    $this->getJson(route('admin.calls.current'))->assertJsonPath('data.id', $mine->id);

    expect(CallRecording::query()->where('status', CallStatusEnum::IN_PROGRESS)->count())->toBe(1);
});

it('does not extend the lock on a reload', function () {
    $mine = CallRecording::factory()->inProgress($this->trimmer)->create(['assigned_at' => now()->subMinutes(10)]);
    $assignedAt = $mine->assigned_at;

    $this->getJson(route('admin.calls.current'))->assertJsonPath('data.id', $mine->id);

    expect($mine->refresh()->assigned_at->equalTo($assignedAt))->toBeTrue();
});

it('serves a call sent back by a moderator before new ones', function () {
    CallRecording::factory()->create();
    $returned = CallRecording::factory()->rejected($this->trimmer)->create();

    $this->getJson(route('admin.calls.current'))
        ->assertJsonPath('data.id', $returned->id)
        ->assertJsonPath('data.status', 'in_progress')
        ->assertJsonPath('data.reject_reason', 'Bası kesilmegen.')
        ->assertJsonPath('data.rejections_count', 1)
        ->assertJsonPath('data.transcript', $returned->transcript);
});

it('never hands out a call that another trimmer holds', function () {
    CallRecording::factory()->inProgress(User::factory()->trimmer()->create())->create();

    $this->getJson(route('admin.calls.current'))->assertSuccessful()->assertJsonPath('data', null);
});

it('hands out a call another trimmer abandoned once the lock runs out', function () {
    $other = User::factory()->trimmer()->create();
    $abandoned = CallRecording::factory()->inProgress($other)->create([
        'assigned_at' => now()->subMinutes(config('calls.lock_ttl_minutes') + 1),
    ]);

    $this->getJson(route('admin.calls.current'))->assertJsonPath('data.id', $abandoned->id);

    expect($abandoned->refresh()->trimmer_id)->toBe($this->trimmer->id);
});

it('keeps a rejected call with its trimmer even after the lock runs out', function () {
    $other = User::factory()->trimmer()->create();
    CallRecording::factory()->inProgress($other)->create([
        'rejections_count' => 1,
        'assigned_at' => now()->subHours(5),
    ]);

    $this->getJson(route('admin.calls.current'))->assertJsonPath('data', null);
});

it('never hands out finished calls', function () {
    CallRecording::factory()->submitted()->create();
    CallRecording::factory()->approved()->create();
    CallRecording::factory()->skipped()->create();

    $this->getJson(route('admin.calls.current'))->assertSuccessful()->assertJsonPath('data', null);
});

it('gives each of two trimmers a different call', function () {
    CallRecording::factory()->count(2)->create();

    $firstId = $this->getJson(route('admin.calls.current'))->json('data.id');

    Sanctum::actingAs(User::factory()->trimmer()->create());
    $secondId = $this->getJson(route('admin.calls.current'))->json('data.id');

    expect($firstId)->not->toBeNull()
        ->and($secondId)->not->toBeNull()
        ->and($secondId)->not->toBe($firstId);
});
