<?php

use App\Enums\CallStatusEnum;
use App\Enums\RoleEnum;
use App\Models\CallRecording;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Sanctum::actingAs(User::factory()->trimmer()->create());
});

function callWithTranscript(string $transcript, CallStatusEnum $status = CallStatusEnum::APPROVED): CallRecording
{
    return CallRecording::factory()->create(['status' => $status, 'transcript' => $transcript]);
}

it('lists the words of checked transcripts, most frequent first', function () {
    callWithTranscript('Assalawma áleykum! Oǵan aytıń, oǵan.');
    callWithTranscript('Assalawma áleykum, oǵan bar.');
    callWithTranscript('Assalawma áleykum. Bar ma?');

    $this->getJson(route('admin.calls.words'))
        ->assertSuccessful()
        ->assertExactJson(['data' => [
            ['word' => 'assalawma', 'count' => 3],
            ['word' => 'oǵan', 'count' => 3],
            ['word' => 'áleykum', 'count' => 3],
        ]]);
});

it('lower-cases words and turns í into ı', function () {
    callWithTranscript('ÁLEYKUM Áleykum áleykum');
    callWithTranscript('Ílaj ılaj ılaj');

    $words = collect($this->getJson(route('admin.calls.words'))->json('data'))->pluck('count', 'word')->all();

    expect($words)->toBe(['áleykum' => 3, 'ılaj' => 3]);
});

it('leaves out rare words, bare numbers and single letters', function () {
    callWithTranscript('saat 15 te a a a 15 15, kelip kelip');
    callWithTranscript('saat 15 te, saat');

    $words = collect($this->getJson(route('admin.calls.words'))->json('data'))->pluck('word')->all();

    expect($words)->toBe(['saat']);
});

it('takes words only from submitted and approved calls', function (CallStatusEnum $status, bool $counted) {
    foreach (range(1, 3) as $ignored) {
        callWithTranscript('telefon', $status);
    }

    $words = collect($this->getJson(route('admin.calls.words'))->json('data'))->pluck('word')->all();

    expect($words)->toBe($counted ? ['telefon'] : []);
})->with([
    'approved' => [CallStatusEnum::APPROVED, true],
    'submitted' => [CallStatusEnum::SUBMITTED, true],
    'rejected' => [CallStatusEnum::REJECTED, false],
    'in progress' => [CallStatusEnum::IN_PROGRESS, false],
    'skipped' => [CallStatusEnum::SKIPPED, false],
]);

it('serves the dictionary from cache instead of rebuilding it on every request', function () {
    foreach (range(1, 3) as $ignored) {
        callWithTranscript('telefon');
    }

    $this->getJson(route('admin.calls.words'))->assertJsonPath('data.0.word', 'telefon');

    foreach (range(1, 3) as $ignored) {
        callWithTranscript('nomer nomer');
    }

    $this->getJson(route('admin.calls.words'))->assertJsonCount(1, 'data');
});

it('caps the dictionary at the configured size', function () {
    config(['calls.words_limit' => 2]);

    foreach (range(1, 3) as $ignored) {
        callWithTranscript('bir eki eki úsh úsh úsh');
    }

    $words = collect($this->getJson(route('admin.calls.words'))->json('data'))->pluck('word')->all();

    expect($words)->toBe(['úsh', 'eki']);
});

it('opens the dictionary to trimmers and admins only', function (RoleEnum $role, bool $allowed) {
    Sanctum::actingAs(User::factory()->create(['role' => $role->value]));

    $response = $this->getJson(route('admin.calls.words'));

    $allowed ? $response->assertSuccessful() : $response->assertForbidden();
})->with([
    'trimmer' => [RoleEnum::TRIMMER, true],
    'admin' => [RoleEnum::ADMIN, true],
    'super admin' => [RoleEnum::SUPER_ADMIN, true],
    'editor' => [RoleEnum::EDITOR, false],
    'speaker' => [RoleEnum::SPEAKER, false],
    'moderator' => [RoleEnum::MODERATOR, false],
]);
