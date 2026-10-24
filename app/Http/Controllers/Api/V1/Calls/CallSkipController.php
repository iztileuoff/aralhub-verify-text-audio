<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Enums\CallEventTypeEnum;
use App\Enums\CallStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Calls\SkipCallRequest;
use App\Http\Resources\V1\Admin\CallResource;
use App\Models\CallRecording;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group(name: 'Calls - Trimming', weight: 60)]
class CallSkipController extends Controller
{
    /**
     * Пометить звонок как непригодный (нет разговора, битый, другой язык…).
     */
    public function __invoke(SkipCallRequest $request, CallRecording $call): JsonResponse
    {
        $userId = $request->user()->id;

        $skipped = $call->changeWhileHeldBy($userId, function (CallRecording $locked) use ($request, $userId): void {
            $locked->fill([
                'status' => CallStatusEnum::SKIPPED,
                'skip_reason' => $request->validated('reason'),
                'skip_comment' => $request->validated('comment'),
                'assigned_at' => null,
            ])->save();

            $locked->logEvent(CallEventTypeEnum::SKIPPED, $userId, $request->validated('comment'));
        });

        if (! $skipped) {
            return response()->json(['message' => 'Этот звонок больше не закреплён за вами.'], 409);
        }

        return (new CallResource($call->load(['trimmer', 'moderator'])))->response();
    }
}
