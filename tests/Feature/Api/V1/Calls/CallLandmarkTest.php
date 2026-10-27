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
    return test()->getJson(route('admin.calls.landmarks.current'))->assertSuccessful()->json('data.id');
}

function labelCall(int $callId, array $payload): TestResponse
{
    return test()->postJson(route('admin.calls.landmark', $callId), $payload);
}

it('is for admins and super admins only', function (RoleEnum $role) {
    Sanctum::actingAs(User::factory()->create(['role' => $role->value]));

    $this->getJson(route('admin.calls.landmarks.current'))->assertForbidden();
    $this->getJson(route('admin.calls.landmarks.index'))->assertForbidden();
})->with([RoleEnum::TRIMMER, RoleEnum::MODERATOR, RoleEnum::SPEAKER, RoleEnum::EDITOR]);

it('hands out only trimmed calls that have no landmark decision yet', function () {
    CallRecording::factory()->create();
    CallRecording::factory()->inProgress()->create();
    CallRecording::factory()->rejected()->create();
    CallRecording::factory()->skipped()->create();
    CallRecording::factory()->approved()->create(['landmark_decision' => CallLandmarkDecisionEnum::NONE]);
    $submitted = CallRecording::factory()->submitted()->create();
    $approved = CallRecording::factory()->approved()->create();

    $this->getJson(route('admin.calls.landmarks.current'))
        ->assertSuccessful()
        ->assertJsonPath('meta.remaining', 2)
        ->assertJsonPath('meta.landmarks_total', 0);

    expect([$submitted->id, $approved->id])->toContain(CallRecording::query()->whereNotNull('landmark_assigned_to')->sole()->id);
});

it('gives the same call back on reload and a different one to another admin', function () {
    CallRecording::factory()->count(2)->submitted()->create();

    $first = takeLandmarkCall();

    expect(takeLandmarkCall())->toBe($first);

    Sanctum::actingAs(User::factory()->admin()->create());

    expect(takeLandmarkCall())->not->toBeNull()->not->toBe($first);
});

it('answers null when there is nothing left to label', function () {
    $this->getJson(route('admin.calls.landmarks.current'))
        ->assertSuccessful()
        ->assertJsonPath('data', null)
        ->assertJsonPath('meta.remaining', 0);
});

it('creates a landmark with the call as its audio', function () {
    CallRecording::factory()->submitted()->create();
    $callId = takeLandmarkCall();

    labelCall($callId, ['decision' => 'primary', 'name' => '  Yurfak '])
        ->assertSuccessful()
        ->assertJsonPath('data.landmark_decision', 'primary')
        ->assertJsonPath('data.landmark.name', 'Yurfak')
        ->assertJsonPath('landmark.examples_count', 1);

    $landmark = CallLandmark::query()->sole();
    $call = CallRecording::query()->find($callId);

    expect($landmark->name)->toBe('Yurfak')
        ->and($landmark->normalized_name)->toBe('yurfak')
        ->and($landmark->call_recording_id)->toBe($callId)
        ->and($landmark->created_by)->toBe($this->admin->id)
        ->and($call->landmark_decision)->toBe(CallLandmarkDecisionEnum::PRIMARY)
        ->and($call->landmark_by)->toBe($this->admin->id)
        ->and($call->landmark_assigned_to)->toBeNull();

    $this->getJson(route('admin.calls.landmarks.current'))->assertJsonPath('data', null);
});

it('does not create a landmark that already exists, whatever the case or spacing', function (string $typed) {
    $yurfak = CallLandmark::factory()->create(['name' => 'Yurfak', 'normalized_name' => 'yurfak']);
    CallRecording::factory()->submitted()->create();
    $callId = takeLandmarkCall();

    labelCall($callId, ['decision' => 'primary', 'name' => $typed])
        ->assertConflict()
        ->assertJsonPath('landmark.id', $yurfak->id)
        ->assertJsonPath('landmark.name', 'Yurfak');

    expect(CallLandmark::query()->count())->toBe(1)
        ->and(CallRecording::query()->find($callId)->landmark_decision)->toBeNull();
})->with(['Yurfak', 'yurfak', 'YURFAK', ' Yurfak.']);

it('skips a call whose landmark is already known, remembering which one', function () {
    $yurfak = CallLandmark::factory()->create(['name' => 'Yurfak', 'normalized_name' => 'yurfak']);
    CallRecording::factory()->submitted()->create();
    $callId = takeLandmarkCall();

    labelCall($callId, ['decision' => 'duplicate', 'landmark_id' => $yurfak->id])
        ->assertSuccessful()
        ->assertJsonPath('data.landmark_decision', 'duplicate')
        ->assertJsonPath('landmark.examples_count', 0)
        ->assertJsonPath('landmark.mentions_count', 1);

    $this->getJson(route('admin.calls.landmarks.current'))->assertJsonPath('data', null);
});

it('keeps a call as one more example of a known landmark', function () {
    $first = CallRecording::factory()->approved()->create(['landmark_decision' => CallLandmarkDecisionEnum::PRIMARY]);
    $yurfak = CallLandmark::factory()->create(['name' => 'Yurfak', 'normalized_name' => 'yurfak', 'call_recording_id' => $first->id]);
    $first->update(['landmark_id' => $yurfak->id]);
    CallRecording::factory()->submitted()->create();
    $callId = takeLandmarkCall();

    labelCall($callId, ['decision' => 'also', 'landmark_id' => $yurfak->id])
        ->assertSuccessful()
        ->assertJsonPath('data.landmark_decision', 'also')
        ->assertJsonPath('landmark.examples_count', 2);
});

it('takes a call without a landmark out of the queue', function () {
    CallRecording::factory()->submitted()->create();
    $callId = takeLandmarkCall();

    labelCall($callId, ['decision' => 'none'])
        ->assertSuccessful()
        ->assertJsonPath('data.landmark_decision', 'none')
        ->assertJsonPath('data.landmark', null);

    $this->getJson(route('admin.calls.landmarks.current'))->assertJsonPath('data', null);
});

it('answers a retried decision with the saved state', function () {
    CallRecording::factory()->submitted()->create();
    $callId = takeLandmarkCall();

    labelCall($callId, ['decision' => 'none'])->assertSuccessful();
    labelCall($callId, ['decision' => 'none'])->assertSuccessful();
});

it('only lets the admin holding the call decide it', function () {
    CallRecording::factory()->submitted()->create();
    $callId = takeLandmarkCall();

    Sanctum::actingAs(User::factory()->admin()->create());

    labelCall($callId, ['decision' => 'none'])->assertConflict();

    expect(CallRecording::query()->find($callId)->landmark_decision)->toBeNull();
});

it('validates the decision', function (array $payload, string $field) {
    $yurfak = CallLandmark::factory()->create();
    CallRecording::factory()->submitted()->create();
    $callId = takeLandmarkCall();

    labelCall($callId, array_map(fn ($value) => $value === 'LANDMARK' ? $yurfak->id : $value, $payload))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'new without a name' => [['decision' => 'primary'], 'name'],
    'cyrillic name' => [['decision' => 'primary', 'name' => 'Юрфак'], 'name'],
    'also without a landmark' => [['decision' => 'also'], 'landmark_id'],
    'unknown landmark' => [['decision' => 'duplicate', 'landmark_id' => 999], 'landmark_id'],
    'name with "none"' => [['decision' => 'none', 'name' => 'Yurfak'], 'name'],
    'unknown decision' => [['decision' => 'maybe'], 'decision'],
]);

it('suggests landmarks as the admin types, prefix matches first', function () {
    foreach (['Bayu bazar', 'Yurfak', 'Yunusabad', 'Aeroport'] as $name) {
        CallLandmark::factory()->create(['name' => $name, 'normalized_name' => CallLandmark::normalize($name)]);
    }

    $this->getJson(route('admin.calls.landmarks.index', ['search' => 'YU']))
        ->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.name', 'Yunusabad')
        ->assertJsonPath('data.1.name', 'Yurfak')
        ->assertJsonPath('data.2.name', 'Bayu bazar');

    $this->getJson(route('admin.calls.landmarks.index', ['search' => '%']))->assertJsonCount(0, 'data');
});

it('lists every landmark with its audio and how often it was heard', function () {
    $call = CallRecording::factory()->approved()->create(['landmark_decision' => CallLandmarkDecisionEnum::PRIMARY]);
    $yurfak = CallLandmark::factory()->create(['name' => 'Yurfak', 'normalized_name' => 'yurfak', 'call_recording_id' => $call->id, 'created_by' => $this->admin->id]);
    $call->update(['landmark_id' => $yurfak->id]);
    CallRecording::factory()->count(2)->approved()->create(['landmark_decision' => CallLandmarkDecisionEnum::DUPLICATE, 'landmark_id' => $yurfak->id]);

    $response = $this->getJson(route('admin.calls.landmarks.index'))
        ->assertSuccessful()
        ->assertJsonPath('data.0.name', 'Yurfak')
        ->assertJsonPath('data.0.examples_count', 1)
        ->assertJsonPath('data.0.mentions_count', 3)
        ->assertJsonPath('data.0.primary_call.id', $call->id)
        ->assertJsonPath('data.0.created_by.id', $this->admin->id);

    expect($response->json('data.0.primary_call.trimmed_url'))->toContain($call->trimmed_path);
});

it('renames a landmark but never into another one', function () {
    $typo = CallLandmark::factory()->create(['name' => 'Yurfk', 'normalized_name' => 'yurfk']);
    CallLandmark::factory()->create(['name' => 'Aeroport', 'normalized_name' => 'aeroport']);

    $this->patchJson(route('admin.calls.landmarks.update', $typo), ['name' => 'Yurfak'])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Yurfak');

    expect($typo->refresh()->normalized_name)->toBe('yurfak');

    $this->patchJson(route('admin.calls.landmarks.update', $typo), ['name' => 'aeroport'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('does not show landmark details to trimmers', function () {
    $trimmer = User::factory()->trimmer()->create();
    CallRecording::factory()->submitted($trimmer)->create(['landmark_decision' => CallLandmarkDecisionEnum::NONE]);
    Sanctum::actingAs($trimmer);

    $response = $this->getJson(route('admin.calls.my'))->assertSuccessful();

    expect($response->json('data.0'))->not->toHaveKey('landmark_decision')->not->toHaveKey('landmark');
});
