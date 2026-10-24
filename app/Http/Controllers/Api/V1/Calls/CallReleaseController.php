<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Enums\CallEventTypeEnum;
use App\Enums\CallStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\CallRecording;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Calls - Trimming', weight: 60)]
class CallReleaseController extends Controller
{
    /**
     * Вернуть звонок в очередь, не сделав (конец смены).
     *
     * Возвращённый модератором так отпустить нельзя — его надо исправить.
     */
    public function __invoke(Request $request, CallRecording $call): JsonResponse
    {
        $userId = $request->user()->id;

        if ($call->trimmer_id === $userId && $call->rejections_count > 0) {
            return response()->json(['message' => 'Звонок возвращён модератором — его нужно исправить, а не отпускать.'], 422);
        }

        $released = $call->changeWhileHeldBy($userId, function (CallRecording $locked) use ($userId): void {
            $locked->logEvent(CallEventTypeEnum::RELEASED, $userId);

            $locked->fill([
                'status' => CallStatusEnum::PENDING,
                'trimmer_id' => null,
                'assigned_at' => null,
            ])->save();
        });

        if (! $released) {
            return response()->json(['message' => 'Этот звонок больше не закреплён за вами.'], 409);
        }

        return response()->json(['message' => 'Звонок возвращён в очередь.']);
    }
}
