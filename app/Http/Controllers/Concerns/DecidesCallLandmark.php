<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\CallLandmarkDecisionEnum;
use App\Http\Resources\V1\Admin\CallLandmarkResource;
use App\Http\Resources\V1\Admin\CallResource;
use App\Models\CallLandmark;
use App\Models\CallRecording;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * The shared part of the three landmark decisions (label / duplicate /
 * empty): only the admin holding the call may decide it, and the outcome
 * codes follow the contract agreed with the frontend (2026-10-27).
 */
trait DecidesCallLandmark
{
    /**
     * @param  Closure(CallRecording): (int|CallLandmark|null)  $landmarkFor  the landmark id to link
     *                                                                        (null for "empty"), or an existing landmark that makes a new one impossible
     */
    protected function decide(int $userId, CallRecording $call, CallLandmarkDecisionEnum $decision, Closure $landmarkFor): JsonResponse
    {
        // A retry of a decision that already went through gets the saved state.
        if ($call->landmark_decision === $decision && $call->landmark_by === $userId) {
            return $this->decided($call);
        }

        $outcome = DB::transaction(function () use ($userId, $call, $decision, $landmarkFor): CallRecording|CallLandmark|string {
            $locked = CallRecording::query()->lockForUpdate()->find($call->id);

            $heldByAnother = $locked->landmark_assigned_to !== null
                && $locked->landmark_assigned_to !== $userId
                && $locked->landmark_assigned_at?->gte(CallRecording::lockExpiredBefore());

            if ($locked->landmark_decision !== null || $heldByAnother) {
                return 'taken';
            }

            if ($locked->landmark_assigned_to !== $userId) {
                return 'not-held';
            }

            $landmark = $landmarkFor($locked);

            if ($landmark instanceof CallLandmark) {
                return $landmark;
            }

            $locked->update([
                'landmark_decision' => $decision,
                'landmark_id' => $landmark,
                'landmark_by' => $userId,
                'landmark_at' => now(),
                'landmark_assigned_to' => null,
                'landmark_assigned_at' => null,
            ]);

            return $locked;
        });

        return match (true) {
            $outcome === 'taken' => response()->json(['message' => 'Этот звонок уже у другого админа или уже размечен.'], 409),
            $outcome === 'not-held' => response()->json(['message' => 'Звонок не в работе у вас — сначала возьмите его.'], 422),
            $outcome instanceof CallLandmark => $this->alreadyExists($outcome),
            default => $this->decided($outcome),
        };
    }

    protected function alreadyExists(CallLandmark $landmark): JsonResponse
    {
        return response()->json([
            'message' => 'Этот ориентир уже есть.',
            'landmark' => new CallLandmarkResource($landmark->loadCount('calls')),
        ], 409);
    }

    private function decided(CallRecording $call): JsonResponse
    {
        return (new CallResource($call->load(['trimmer', 'moderator', 'landmark'])))->response();
    }
}
