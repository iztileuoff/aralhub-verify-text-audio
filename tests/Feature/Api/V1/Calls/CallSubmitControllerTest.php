<?php

use App\Enums\CallEventTypeEnum;
use App\Enums\CallStatusEnum;
use App\Models\CallRecording;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('calls');

    $this->trimmer = User::factory()->trimmer()->create();
    Sanctum::actingAs($this->trimmer);

    $this->call = CallRecording::factory()->inProgress($this->trimmer)->create();
});

function submitPayload(array $overrides = []): array
{
    return [
        'audio' => fakeWav(seconds: 2.5),
        'transcript' => 'Assalawma áleykum, qalay jaǵdayıńız?',
        'source_duration_ms' => 9000,
        'trim_start_ms' => 1500,
        'trim_end_ms' => 4000,
        ...$overrides,
    ];
}

function submitCall(CallRecording $call, array $overrides = []): TestResponse
{
    return test()->post(route('admin.calls.submit', $call), submitPayload($overrides), ['Accept' => 'application/json']);
}

it('saves the trimmed audio and the transcript and sends the call to moderation', function () {
    submitCall($this->call)
        ->assertCreated()
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.trimmed_duration', 40000)
        ->assertJsonPath('data.trimmed_duration_seconds', 2.5)
        ->assertJsonPath('data.trim_start_ms', 1500)
        ->assertJsonPath('data.trim_end_ms', 4000)
        ->assertJsonPath('data.source_duration_ms', 9000);

    $call = $this->call->refresh();

    expect($call->status)->toBe(CallStatusEnum::SUBMITTED)
        ->and($call->submitted_at)->not->toBeNull()
        ->and($call->trimmed_path)->toStartWith('trimmed/'.$call->id.'_')->toEndWith('.wav')
        ->and($call->transcript)->toBe('Assalawma áleykum, qalay jaǵdayıńız?')
        ->and($call->normalized_transcript)->toBe('assalawma áleykum, qalay jaǵdayıńız')
        ->and($call->tokenized_transcript)->toBe('a s s a l a w m a | á l e y k u m , | q a l a y | j a ǵ d a y ı ń ı z |');

    Storage::disk('calls')->assertExists($call->trimmed_path);

    $event = $call->events()->sole();

    expect($event->type)->toBe(CallEventTypeEnum::SUBMITTED)
        ->and($event->user_id)->toBe($this->trimmer->id)
        ->and($event->trimmer_id)->toBe($this->trimmer->id)
        ->and($event->trimmed_duration)->toBe(40000);
});

it('measures the duration from the file, not from anything the client says', function () {
    submitCall($this->call, ['audio' => fakeWav(seconds: 1, listChunk: true)])
        ->assertCreated()
        ->assertJsonPath('data.trimmed_duration', 16000);
});

it('lower-cases Í to the dotless ı and folds line breaks into spaces', function () {
    submitCall($this->call, ['transcript' => "ÍSSı  suwıq\nkún, Qızıl."])->assertCreated();

    $call = $this->call->refresh();

    expect($call->transcript)->toBe('ÍSSı suwıq kún, Qızıl.')
        ->and($call->normalized_transcript)->toBe('ıssı suwıq kún, qızıl')
        ->and($call->tokenized_transcript)->toBe('ı s s ı | s u w ı q | k ú n , | q ı z ı l |');
});

it('turns the dotted í into ı before storing', function () {
    submitCall($this->call, ['transcript' => 'Qízíl'])->assertCreated();

    expect($this->call->refresh()->transcript)->toBe('Qızıl');
});

it('rejects audio that is not 16 kHz mono 16-bit PCM', function (array $wav, string $expected) {
    submitCall($this->call, ['audio' => fakeWav(...$wav)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('audio');

    expect(submitCall($this->call, ['audio' => fakeWav(...$wav)])->json('errors.audio.0'))->toContain($expected)
        ->and($this->call->refresh()->status)->toBe(CallStatusEnum::IN_PROGRESS);
})->with([
    '44.1 kHz' => [['sampleRate' => 44100], '44100 Hz'],
    '8 kHz' => [['sampleRate' => 8000], '8000 Hz'],
    'stereo' => [['channels' => 2], 'каналов: 2'],
    '24-bit' => [['bits' => 24], '24 бит'],
    'float' => [['bits' => 32, 'format' => 3], 'не PCM'],
]);

it('rejects a file that is not a wav at all', function () {
    submitCall($this->call, ['audio' => UploadedFile::fake()->createWithContent('call.wav', 'ID3 not really audio')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['audio' => 'Файл не похож на WAV.']);
});

it('rejects a result longer than the limit', function () {
    config(['calls.max_duration_seconds' => 3]);

    submitCall($this->call, ['audio' => fakeWav(seconds: 3.5)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('audio');
});

it('rejects an empty recording', function () {
    submitCall($this->call, ['audio' => fakeWav(seconds: 0)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['audio' => 'В файле нет звука.']);
});

it('refuses a transcript that is not Karakalpak Latin', function (string $transcript, string $message) {
    submitCall($this->call, ['transcript' => $transcript])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['transcript' => $message]);
})->with([
    'cyrillic' => ['Ассалаўма әлейкум', 'Текст пишется латиницей: кириллица не допускается.'],
    'mixed cyrillic' => ['Qalay жаǵday', 'Текст пишется латиницей: кириллица не допускается.'],
    'digits' => ['Saat 15 te', 'Числа пишутся словами: цифры не допускаются.'],
    'other alphabets' => ['Qalay ş ö', 'Недопустимые символы: ş ö.'],
    'markup' => ['Qalay [shum]', 'Недопустимые символы: [ ].'],
    'punctuation only' => ['...', 'В тексте нет ни одного слова.'],
]);

it('validates the trim points', function (array $overrides, string $field) {
    submitCall($this->call, $overrides)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'end before start' => [['trim_start_ms' => 4000, 'trim_end_ms' => 1000], 'trim_end_ms'],
    'end after the source' => [['trim_end_ms' => 9500], 'trim_end_ms'],
    'negative start' => [['trim_start_ms' => -1], 'trim_start_ms'],
    'no source duration' => [['source_duration_ms' => null], 'source_duration_ms'],
]);

it('answers a retried submission with the saved state', function () {
    submitCall($this->call)->assertCreated();
    $path = $this->call->refresh()->trimmed_path;

    submitCall($this->call)->assertCreated()->assertJsonPath('data.status', 'submitted');

    expect($this->call->refresh()->trimmed_path)->toBe($path)
        ->and($this->call->events()->count())->toBe(1)
        ->and(Storage::disk('calls')->allFiles('trimmed'))->toHaveCount(1);
});

it('refuses a call that is not held by the trimmer', function (CallRecording $call) {
    submitCall($call)
        ->assertConflict()
        ->assertJsonPath('message', 'Этот звонок больше не закреплён за вами.');

    expect(Storage::disk('calls')->allFiles('trimmed'))->toBeEmpty();
})->with([
    'someone else holds it' => fn () => CallRecording::factory()->inProgress(User::factory()->trimmer()->create())->create(),
    'never taken' => fn () => CallRecording::factory()->create(),
    'already approved' => fn () => CallRecording::factory()->approved(test()->trimmer)->create(),
]);

it('replaces the earlier result when a rejected call is resubmitted', function () {
    $this->call->delete();
    $call = CallRecording::factory()->rejected($this->trimmer)->create();
    Storage::disk('calls')->put($call->trimmed_path, 'old');
    $oldPath = $call->trimmed_path;

    $this->getJson(route('admin.calls.current'))->assertJsonPath('data.id', $call->id);

    submitCall($call)->assertCreated();

    expect($call->refresh()->trimmed_path)->not->toBe($oldPath)
        ->and($call->rejections_count)->toBe(1);

    Storage::disk('calls')->assertMissing($oldPath);
    Storage::disk('calls')->assertExists($call->trimmed_path);
});
