<?php

use App\Enums\CallStatusEnum;
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

/**
 * Store the same transcript in several approved calls.
 */
function transcriptTimes(string $transcript, int $times = 3): void
{
    CallRecording::factory()->count($times)->create([
        'status' => CallStatusEnum::APPROVED,
        'transcript' => $transcript,
    ]);
}

/**
 * @return array<int, string>
 */
function nextWords(array $query): array
{
    return collect(test()->getJson(route('admin.calls.words', $query))->assertSuccessful()->json('data'))
        ->pluck('word')
        ->all();
}

it('suggests the words that most often follow the previous word', function () {
    transcriptTimes('Assalawma áleykum. Assalawma áleykum. Assalawma qalay?');

    $response = $this->getJson(route('admin.calls.words', ['prev' => 'assalawma']))->assertSuccessful();

    expect($response->json('data'))->toBe([
        ['word' => 'áleykum', 'count' => 6],
        ['word' => 'qalay', 'count' => 3],
    ]);
});

it('returns nothing instead of random frequent words when the previous word has no known continuation', function () {
    transcriptTimes('Assalawma áleykum, taksi kerek.');

    expect(nextWords(['prev' => 'raxmet']))->toBe([])
        ->and(nextWords(['prev' => 'kerek']))->toBe([]);
});

it('leaves out pairs seen fewer than three times', function () {
    transcriptTimes('alo taksi', 3);
    transcriptTimes('alo bazar', 2);

    expect(nextWords(['prev' => 'alo']))->toBe(['taksi']);
});

it('does not pair words across a sentence end, but does across a comma', function () {
    transcriptTimes('Raxmet. Sizge keldim. Hawa, keldim! Jaqsı');

    expect(nextWords(['prev' => 'raxmet']))->toBe([])
        ->and(nextWords(['prev' => 'hawa']))->toBe(['keldim'])
        ->and(nextWords(['prev' => 'keldim']))->toBe([]);
});

it('does not pair words a number stands between', function () {
    transcriptTimes('saat 15 te kelemen');

    expect(nextWords(['prev' => 'saat']))->toBe([])
        ->and(nextWords(['prev' => 'te']))->toBe(['kelemen']);
});

it('prefers the continuation of both previous words, then fills up from the last one', function () {
    transcriptTimes('qaysı kóshe kerek', 3);
    transcriptTimes('qaysı kóshe bar', 3);
    transcriptTimes('bul kóshe emes', 5);

    expect(nextWords(['prev' => 'qaysı kóshe']))->toBe(['bar', 'kerek', 'emes'])
        ->and(nextWords(['prev' => 'bul kóshe']))->toBe(['emes', 'bar', 'kerek']);
});

it('matches the previous words without capitals and special letters', function () {
    transcriptTimes('Assalawma áleykum, qayerge barasız');

    expect(nextWords(['prev' => 'aleykum']))->toBe(['qayerge'])
        ->and(nextWords(['prev' => 'ÁLEYKUM']))->toBe(['qayerge'])
        ->and(nextWords(['prev' => 'assalawma aleykum']))->toBe(['qayerge']);
});

it('narrows the continuations to the started word, then fills up with frequent words by prefix', function () {
    transcriptTimes('alo taksi kerek, taxta qayda, taqıya');
    transcriptTimes('alo tamaq', 3);
    transcriptTimes('taza taza taza', 3);

    expect(nextWords(['prev' => 'alo', 'prefix' => 'ta']))->toBe(['taksi', 'tamaq', 'taza', 'taqıya', 'taxta']);
});

it('accepts a one-letter start of the next word after the previous words', function () {
    transcriptTimes('alo taksi, alo bazar, bazar');

    expect(nextWords(['prev' => 'alo', 'prefix' => 't']))->toBe(['taksi']);
});

it('respects the limit for next-word suggestions', function () {
    transcriptTimes('alo aa1 alo bb1 alo cc1 alo dd1 alo ee1 alo ff1 alo gg1 alo hh1');

    expect(nextWords(['prev' => 'alo']))->toHaveCount(6)
        ->and(nextWords(['prev' => 'alo', 'limit' => 2]))->toHaveCount(2);
});

it('still lists the whole dictionary when nothing is sent, for the frontend already deployed', function () {
    transcriptTimes('alo taksi');

    expect(nextWords([]))->toBe(['alo', 'taksi']);
});

it('rejects previous words that are too long', function () {
    $this->getJson(route('admin.calls.words', ['prev' => str_repeat('a', 101)]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('prev');
});
