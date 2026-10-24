<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Enums\CallEventTypeEnum;
use App\Enums\CallStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\CallResource;
use App\Models\CallRecording;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

#[Group(name: 'Calls - Moderation', weight: 61)]
class CallRestoreController extends Controller
{
    /**
     * Вернуть звонок, помеченный непригодным, обратно в очередь.
     */
    public function __invoke(Request $request, CallRecording $call): JsonResponse
    {
        $adminId = $request->user()->id;

        $restored = DB::transaction(function () use ($call, $adminId): bool {
            $locked = CallRecording::query()->lockForUpdate()->find($call->id);

            if ($locked?->status !== CallStatusEnum::SKIPPED) {
                return false;
            }

            $locked->logEvent(CallEventTypeEnum::RESTORED, $adminId, $locked->skip_reason?->value);

            $locked->fill([
                'status' => CallStatusEnum::PENDING,
                'trimmer_id' => null,
                'assigned_at' => null,
                'skip_reason' => null,
                'skip_comment' => null,
            ])->save();

            return true;
        });

        if (! $restored) {
            return response()->json(['message' => 'Вернуть в очередь можно только непригодный звонок.'], 422);
        }

        return (new CallResource($call->refresh()->load(['trimmer', 'moderator'])))->response();
    }
}
