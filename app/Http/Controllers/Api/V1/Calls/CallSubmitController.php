<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Enums\CallEventTypeEnum;
use App\Enums\CallStatusEnum;
use App\Http\Controllers\Concerns\DerivesLatinTranscripts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Calls\SubmitCallRequest;
use App\Http\Resources\V1\Admin\CallResource;
use App\Models\CallRecording;
use App\Rules\Pcm16kMonoWavRule;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

#[Group(name: 'Calls - Trimming', weight: 60)]
class CallSubmitController extends Controller
{
    use DerivesLatinTranscripts;

    /**
     * Сдать обрезанный звонок (WAV 16 kHz mono 16-bit) с текстом — уходит на модерацию.
     */
    public function __invoke(SubmitCallRequest $request, CallRecording $call): JsonResponse
    {
        $userId = $request->user()->id;

        // Clients resend the upload after a timeout; a retry of a submission
        // that already went through gets the saved state, not an error.
        if ($call->status === CallStatusEnum::SUBMITTED && $call->trimmer_id === $userId) {
            return $this->created($call);
        }

        if (! $call->isHeldBy($userId)) {
            return $this->notYours();
        }

        $disk = CallRecording::disk();
        $audio = $request->file('audio');

        try {
            $path = $audio->storeAs('trimmed', $call->id.'_'.Str::random(20).'.wav', $disk);
        } catch (Throwable $exception) {
            report($exception);
            $path = false;
        }

        if ($path === false) {
            Log::error('Trimmed call upload failed', ['disk' => $disk, 'call_id' => $call->id, 'user_id' => $userId]);

            return response()->json(['message' => 'Не удалось сохранить аудио. Попробуйте ещё раз.'], 503);
        }

        $previousPath = $call->trimmed_path;
        $wav = Pcm16kMonoWavRule::inspect($audio->getRealPath());

        $saved = $call->changeWhileHeldBy($userId, function (CallRecording $locked) use ($request, $path, $wav, $userId): void {
            $locked->fill([
                ...$this->deriveLatinTranscripts($request->validated('transcript')),
                'status' => CallStatusEnum::SUBMITTED,
                'source_duration_ms' => $request->validated('source_duration_ms'),
                'trim_start_ms' => $request->validated('trim_start_ms'),
                'trim_end_ms' => $request->validated('trim_end_ms'),
                'trimmed_path' => $path,
                'trimmed_duration' => Pcm16kMonoWavRule::samples($wav),
                'submitted_at' => now(),
            ])->save();

            $locked->logEvent(CallEventTypeEnum::SUBMITTED, $userId);
        });

        if (! $saved) {
            Storage::disk($disk)->delete($path);

            return $this->notYours();
        }

        // A resubmission after a rejection replaces the earlier result.
        if ($previousPath && $previousPath !== $path) {
            Storage::disk($disk)->delete($previousPath);
        }

        return $this->created($call);
    }

    private function created(CallRecording $call): JsonResponse
    {
        return (new CallResource($call->load(['trimmer', 'moderator'])))->response()->setStatusCode(201);
    }

    private function notYours(): JsonResponse
    {
        return response()->json(['message' => 'Этот звонок больше не закреплён за вами.'], 409);
    }
}
