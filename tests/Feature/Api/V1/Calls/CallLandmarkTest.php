<?php

use App\Enums\CallLandmarkDecisionEnum;
use App\Enums\RoleEnum;
use App\Models\CallLandmark;
use App\Models\CallRecording;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('calls');

    $this->admin = User::factory()->admin()->create();
    Sanctum::actingAs($this->admin);
});

/**
 * Take the current landmark call as the acting admin and return its id.
 */
function takeLandmarkCall(): ?int
{
    return test()->getJson(route('admin.landmarks.calls.current'))->assertSuccessful()->json('data.id');
}

function decideCall(string $action, int $callId, array $payload = []): TestResponse
{
    return test()->postJson(route('admin.landmarks.calls.'.$action, $callId), $payload);
}

function landmark(string $name, array $attributes = []): CallLandmark
{
    return CallLandmark::factory()->create(['name' => $name, 'normalized_name' => CallLandmark::normalize($name), ...$attributes]);
}

it('is for admins and super admins only', function (RoleEnum $role) {
    Sanctum::actingAs(User::factory()->create(['role' => $role->value]));

    $this->getJson(route('admin.landmarks.calls.current'))->assertForbidden();
    $this->getJson(route('admin.landmarks.index'))->assertForbidden();
    $this->getJson(route('admin.landmarks.stats'))->assertForbidden();
})->with([RoleEnum::TRIMMER, RoleEnum::MODERATOR, RoleEnum::SPEAKER, RoleEnum::EDITOR]);

it('hands out any call not yet decided, whatever its trimming state, except unusable ones', function () {
    $pending = CallRecording::factory()->create();
    $trimming = CallRecording::factory()->inProgress()->create();
    CallRecording::factory()->skipped()->create();
    CallRecording::factory()->submitted()->create(['landmark_decision' => CallLandmarkDecisionEnum::EMPTY]);

    $first = takeLandmarkCall();

    Sanctum::actingAs(User::factory()->admin()->create());
    $second = takeLandmarkCall();

    Sanctum::actingAs(User::factory()->admin()->create());

    expect([$first, $second])->toEqualCanonicalizing([$pending->id, $trimming->id])
        ->and(takeLandmarkCall())->toBeNull();
});

it('returns the call with its source url and status in progress', function () {
    CallRecording::factory()->create(['source_path' => 'source/batch-1/call-000007.mp3']);

    $response = $this->getJson(route('admin.landmarks.calls.current'))
        ->assertSuccessful()
        ->assertJsonPath('data.landmark_status', 'in_progress')
        ->assertJsonPath('data.landmark', null);

    expect($response->json('data.source_url'))->toContain('source/batch-1/call-000007.mp3');
});

it('gives the same call back on reload without extending the hold', function () {
    CallRecording::factory()->count(2)->create();

    $first = takeLandmarkCall();
    $heldAt = CallRecording::query()->find($first)->landmark_assigned_at;

    $this->travel(5)->minutes();

    expect(takeLandmarkCall())->toBe($first)
        ->and(CallRecording::query()->find($first)->landmark_assigned_at->equalTo($heldAt))->toBeTrue();
});

it('puts an abandoned call back into the queue after the hold expires', function () {
    CallRecording::factory()->create();
    $callId = takeLandmarkCall();

    $this->travel(config('calls.lock_ttl_minutes') + 1)->minutes();
    Sanctum::actingAs($other = User::factory()->admin()->create());

    expect(takeLandmarkCall())->toBe($callId)
        ->and(CallRecording::query()->find($callId)->landmark_assigned_to)->toBe($other->id);
});

it('answers null when everything is listened to', function () {
    $this->getJson(route('admin.landmarks.calls.current'))->assertSuccessful()->assertExactJson(['data' => null]);
});

it('creates a new landmark from the call', function () {
    CallRecording::factory()->create();
    $callId = takeLandmarkCall();

    decideCall('label', $callId, ['name' => '  Юрфак  '])
        ->assertSuccessful()
        ->assertJsonPath('data.landmark_status', 'labeled')
        ->assertJsonPath('data.landmark.name', 'Юрфак');

    $landmark = CallLandmark::query()->sole();
    $call = CallRecording::query()->find($callId);

    expect($landmark->normalized_name)->toBe('юрфак')
        ->and($landmark->call_recording_id)->toBe($callId)
        ->and($landmark->created_by)->toBe($this->admin->id)
        ->and($call->landmark_decision)->toBe(CallLandmarkDecisionEnum::LABELED)
        ->and($call->landmark_id)->toBe($landmark->id)
        ->and($call->landmark_by)->toBe($this->admin->id)
        ->and($call->landmark_assigned_to)->toBeNull();

    expect(takeLandmarkCall())->toBeNull();
});

it('refuses a landmark that already exists, whatever the case, spacing or ё', function (string $typed) {
    $existing = landmark('Юрфак');
    CallRecording::factory()->create();
    $callId = takeLandmarkCall();

    decideCall('label', $callId, ['name' => $typed])
        ->assertConflict()
        ->assertJsonPath('message', 'Этот ориентир уже есть.')
        ->assertJsonPath('landmark.id', $existing->id)
        ->assertJsonPath('landmark.calls_count', 0);

    expect(CallLandmark::query()->count())->toBe(1)
        ->and(CallRecording::query()->find($callId)->landmark_decision)->toBeNull();
})->with(['Юрфак', ' юрфак ', 'ЮРФАК', 'юрфак.']);

it('treats ё and е, Í and ı as the same letter', function () {
    expect(CallLandmark::normalize('Тёплый  Стан'))->toBe('теплый стан')
        ->and(CallLandmark::normalize('Íshki bazar'))->toBe('ıshki bazar')
        ->and(CallLandmark::normalize('íshki bazar'))->toBe('ıshki bazar');
});

it('links a call to a landmark that is already known', function () {
    $yurfak = landmark('Yurfak');
    CallRecording::factory()->create();
    $callId = takeLandmarkCall();

    decideCall('duplicate', $callId, ['landmark_id' => $yurfak->id])
        ->assertSuccessful()
        ->assertJsonPath('data.landmark_status', 'duplicate')
        ->assertJsonPath('data.landmark.id', $yurfak->id);

    expect(takeLandmarkCall())->toBeNull();
});

it('marks a call without a landmark', function () {
    CallRecording::factory()->create();
    $callId = takeLandmarkCall();

    decideCall('empty', $callId)
        ->assertSuccessful()
        ->assertJsonPath('data.landmark_status', 'empty')
        ->assertJsonPath('data.landmark', null);

    expect(takeLandmarkCall())->toBeNull();
});

it('answers a retried decision with the saved state', function () {
    CallRecording::factory()->create();
    $callId = takeLandmarkCall();

    decideCall('empty', $callId)->assertSuccessful();
    decideCall('empty', $callId)->assertSuccessful()->assertJsonPath('data.landmark_status', 'empty');
});

it('answers 409 when the hold expired and another admin took the call', function () {
    CallRecording::factory()->create();
    $callId = takeLandmarkCall();

    $this->travel(config('calls.lock_ttl_minutes') + 1)->minutes();
    Sanctum::actingAs(User::factory()->admin()->create());
    takeLandmarkCall();
    Sanctum::actingAs($this->admin);

    decideCall('label', $callId, ['name' => 'Yurfak'])
        ->assertConflict()
        ->assertJsonMissingPath('landmark');
});

it('answers 422 for a call the admin never took', function () {
    $call = CallRecording::factory()->create();

    decideCall('empty', $call->id)->assertUnprocessable();

    expect($call->refresh()->landmark_decision)->toBeNull();
});

it('validates the name and the landmark', function (string $action, array $payload, string $field) {
    CallRecording::factory()->create();
    $callId = takeLandmarkCall();

    decideCall($action, $callId, $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'no name' => ['label', [], 'name'],
    'too short' => ['label', ['name' => ' a '], 'name'],
    'too long' => ['label', ['name' => str_repeat('a', 101)], 'name'],
    'no landmark' => ['duplicate', [], 'landmark_id'],
    'unknown landmark' => ['duplicate', ['landmark_id' => 999], 'landmark_id'],
]);

it('suggests by substring: exact match first, then the most frequent', function () {
    $flag = landmark('флаг');
    landmark('Флагшток');
    $popular = landmark('Новый флаг');
    landmark('Aeroport');
    CallRecording::factory()->count(3)->create(['landmark_decision' => CallLandmarkDecisionEnum::DUPLICATE, 'landmark_id' => $popular->id]);

    $this->getJson(route('admin.landmarks.index', ['search' => 'ФЛАГ', 'per_page' => 8]))
        ->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.id', $flag->id)
        ->assertJsonPath('data.1.name', 'Новый флаг')
        ->assertJsonPath('data.1.calls_count', 3)
        ->assertJsonPath('data.2.name', 'Флагшток');

    $this->getJson(route('admin.landmarks.index', ['search' => '%']))->assertJsonCount(0, 'data');
});

it('lists every landmark, newest first, with who added it', function () {
    landmark('Aeroport', ['created_at' => now()->subDay()]);
    landmark('Yurfak', ['created_by' => $this->admin->id]);

    $this->getJson(route('admin.landmarks.index', ['per_page' => 50]))
        ->assertSuccessful()
        ->assertJsonPath('data.0.name', 'Yurfak')
        ->assertJsonPath('data.0.created_by.id', $this->admin->id)
        ->assertJsonPath('data.1.name', 'Aeroport');
});

it('counts the queue', function () {
    CallRecording::factory()->count(3)->create();
    CallRecording::factory()->skipped()->create();
    CallRecording::factory()->create(['landmark_decision' => CallLandmarkDecisionEnum::LABELED]);
    CallRecording::factory()->count(2)->create(['landmark_decision' => CallLandmarkDecisionEnum::DUPLICATE]);
    CallRecording::factory()->create(['landmark_decision' => CallLandmarkDecisionEnum::EMPTY]);
    landmark('Yurfak');
    takeLandmarkCall();

    $this->getJson(route('admin.landmarks.stats'))
        ->assertSuccessful()
        ->assertExactJson(['data' => [
            'calls_total' => 7,
            'pending' => 2,
            'in_progress' => 1,
            'labeled' => 1,
            'duplicate' => 2,
            'empty' => 1,
            'landmarks_total' => 1,
        ]]);
});

it('renames a landmark but never into another one', function () {
    $typo = landmark('Yurfk');
    landmark('Aeroport');

    $this->patchJson(route('admin.landmarks.update', $typo), ['name' => 'Yurfak'])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Yurfak');

    $this->patchJson(route('admin.landmarks.update', $typo), ['name' => 'AEROPORT'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('does not show landmark fields to trimmers', function () {
    $trimmer = User::factory()->trimmer()->create();
    CallRecording::factory()->submitted($trimmer)->create(['landmark_decision' => CallLandmarkDecisionEnum::EMPTY]);
    Sanctum::actingAs($trimmer);

    expect($this->getJson(route('admin.calls.my'))->json('data.0'))
        ->not->toHaveKey('landmark_status')
        ->not->toHaveKey('landmark');
});
