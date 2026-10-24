<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Enums\CallStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\CallResource;
use App\Models\CallRecording;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Calls - Trimming', weight: 60)]
class CallCurrentController extends Controller
{
    /** How many times to retry when another trimmer grabs the same call first. */
    private const TAKE_ATTEMPTS = 10;

    /**
     * Звонок, над которым обрезчик работает сейчас; если такого нет — бронирует следующий.
     *
     * Порядок: свой в работе → свой возвращённый модератором → новый из очереди.
     * `{"data": null}` — очередь пуста.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $call = $this->resumeOwn($userId) ?? $this->takeNext($userId);

        if (! $call) {
            return response()->json(['data' => null]);
        }

        return (new CallResource($call->load(['trimmer', 'moderator'])))->response();
    }

    /**
     * The trimmer's own unfinished call: the one in progress (a page reload
     * must bring back the same call), otherwise the oldest one sent back by
     * a moderator. Re-taking it renews an expired lock.
     */
    private function resumeOwn(int $userId): ?CallRecording
    {
        $own = CallRecording::query()
            ->where('trimmer_id', $userId)
            ->whereIn('status', [CallStatusEnum::IN_PROGRESS, CallStatusEnum::REJECTED])
            ->orderByRaw('case when status = ? then 0 else 1 end', [CallStatusEnum::IN_PROGRESS->value])
            ->orderBy('moderated_at')
            ->orderBy('id')
            ->first();

        if (! $own) {
            return null;
        }

        $lockIsFresh = $own->status === CallStatusEnum::IN_PROGRESS
            && $own->assigned_at?->gte(CallRecording::lockExpiredBefore());

        if ($lockIsFresh) {
            return $own;
        }

        // Conditional, so a call someone else has just taken over is not stolen back.
        $renewed = CallRecording::query()
            ->whereKey($own->id)
            ->where('trimmer_id', $userId)
            ->whereIn('status', [CallStatusEnum::IN_PROGRESS, CallStatusEnum::REJECTED])
            ->update([
                'status' => CallStatusEnum::IN_PROGRESS,
                'assigned_at' => now(),
            ]);

        return $renewed === 1 ? $own->refresh() : null;
    }

    /**
     * Book the next free call. The booking is a conditional update rather
     * than a read-then-write, so when five trimmers ask at once each call
     * still goes to exactly one of them; the loser simply tries the next.
     */
    private function takeNext(int $userId): ?CallRecording
    {
        for ($attempt = 0; $attempt < self::TAKE_ATTEMPTS; $attempt++) {
            $id = CallRecording::query()->available()->orderBy('id')->value('id');

            if ($id === null) {
                return null;
            }

            $taken = CallRecording::query()
                ->whereKey($id)
                ->available()
                ->update([
                    'status' => CallStatusEnum::IN_PROGRESS,
                    'trimmer_id' => $userId,
                    'assigned_at' => now(),
                ]);

            if ($taken === 1) {
                return CallRecording::query()->find($id);
            }
        }

        return null;
    }
}
