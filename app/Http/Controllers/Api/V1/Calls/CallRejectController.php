<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Enums\CallEventTypeEnum;
use App\Enums\CallStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Calls\RejectCallRequest;
use App\Http\Resources\V1\Admin\CallResource;
use App\Models\CallRecording;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group(name: 'Calls - Moderation', weight: 61)]
class CallRejectController extends Controller
{
    /**
     * Вернуть звонок тому же обрезчику с причиной — он получит его первым в очереди.
     */
    public function __invoke(RejectCallRequest $request, CallRecording $call): JsonResponse
    {
        $moderatorId = $request->user()->id;
        $reason = $request->validated('reason');

        $rejected = $call->changeWhileSubmitted(function (CallRecording $locked) use ($moderatorId, $reason): void {
            $locked->fill([
                'status' => CallStatusEnum::REJECTED,
                'reject_reason' => $reason,
                'rejections_count' => $locked->rejections_count + 1,
                'moderator_id' => $moderatorId,
                'moderated_at' => now(),
                'assigned_at' => null,
            ])->save();

            $locked->logEvent(CallEventTypeEnum::REJECTED, $moderatorId, $reason);
        });

        if (! $rejected) {
            return response()->json(['message' => 'Звонок не ждёт модерации.'], 422);
        }

        return (new CallResource($call->load(['trimmer', 'moderator'])))->response();
    }
}
