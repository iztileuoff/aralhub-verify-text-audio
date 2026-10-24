<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Enums\CallEventTypeEnum;
use App\Enums\CallStatusEnum;
use App\Http\Controllers\Concerns\DerivesLatinTranscripts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Calls\ApproveCallRequest;
use App\Http\Resources\V1\Admin\CallResource;
use App\Models\CallRecording;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group(name: 'Calls - Moderation', weight: 61)]
class CallApproveController extends Controller
{
    use DerivesLatinTranscripts;

    /**
     * Принять звонок; можно сразу поправить опечатки в тексте.
     */
    public function __invoke(ApproveCallRequest $request, CallRecording $call): JsonResponse
    {
        $moderatorId = $request->user()->id;
        $transcript = $request->validated('transcript');

        $approved = $call->changeWhileSubmitted(function (CallRecording $locked) use ($moderatorId, $transcript): void {
            $edited = $transcript !== null && $transcript !== $locked->transcript;

            $locked->fill([
                ...($edited ? $this->deriveLatinTranscripts($transcript) : []),
                'status' => CallStatusEnum::APPROVED,
                'moderator_id' => $moderatorId,
                'moderated_at' => now(),
            ])->save();

            $locked->logEvent(CallEventTypeEnum::APPROVED, $moderatorId, $edited ? 'transcript edited' : null);
        });

        if (! $approved) {
            return response()->json(['message' => 'Звонок не ждёт модерации.'], 422);
        }

        return (new CallResource($call->load(['trimmer', 'moderator'])))->response();
    }
}
