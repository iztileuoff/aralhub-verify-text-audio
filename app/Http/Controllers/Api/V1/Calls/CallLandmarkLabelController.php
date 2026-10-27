<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Enums\CallLandmarkDecisionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Calls\LabelCallLandmarkRequest;
use App\Http\Resources\V1\Admin\CallLandmarkResource;
use App\Http\Resources\V1\Admin\CallResource;
use App\Models\CallLandmark;
use App\Models\CallRecording;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

#[Group(name: 'Calls - Landmarks', weight: 62)]
class CallLandmarkLabelController extends Controller
{
    /**
     * Решение по ориентиру звонка: новый ориентир, «это тоже X», «X уже есть — пропустить» или «ориентира нет».
     *
     * Новый ориентир с названием, которое уже есть (без учёта регистра и
     * пробелов), не создаётся: `409` с этим ориентиром — пропустить или «тоже X».
     */
    public function __invoke(LabelCallLandmarkRequest $request, CallRecording $call): JsonResponse
    {
        $userId = $request->user()->id;
        $decision = CallLandmarkDecisionEnum::from($request->validated('decision'));

        // A retry of a decision that already went through gets the saved state.
        if ($call->landmark_decision === $decision && $call->landmark_by === $userId) {
            return $this->decided($call);
        }

        try {
            $outcome = DB::transaction(function () use ($request, $call, $decision, $userId): CallRecording|CallLandmark|string {
                $locked = CallRecording::query()->landmarkPending()->lockForUpdate()->find($call->id);

                if (! $locked || $locked->landmark_assigned_to !== $userId) {
                    return 'not-yours';
                }

                $landmarkId = $request->validated('landmark_id');

                if ($decision === CallLandmarkDecisionEnum::PRIMARY) {
                    $name = $request->validated('name');
                    $existing = CallLandmark::query()->where('normalized_name', CallLandmark::normalize($name))->first();

                    if ($existing) {
                        return $existing;
                    }

                    $landmarkId = CallLandmark::query()->create([
                        'name' => $name,
                        'normalized_name' => CallLandmark::normalize($name),
                        'call_recording_id' => $locked->id,
                        'created_by' => $userId,
                    ])->id;
                }

                $locked->update([
                    'landmark_decision' => $decision,
                    'landmark_id' => $decision === CallLandmarkDecisionEnum::NONE ? null : $landmarkId,
                    'landmark_by' => $userId,
                    'landmark_at' => now(),
                    'landmark_assigned_to' => null,
                    'landmark_assigned_at' => null,
                ]);

                return $locked;
            });
        } catch (UniqueConstraintViolationException) {
            // Another admin created the same landmark a moment earlier.
            $outcome = CallLandmark::query()->where('normalized_name', CallLandmark::normalize($request->validated('name')))->firstOrFail();
        }

        if ($outcome === 'not-yours') {
            return response()->json(['message' => 'Этот звонок уже не в вашей очереди ориентиров.'], 409);
        }

        if ($outcome instanceof CallLandmark) {
            return response()->json([
                'message' => "Ориентир «{$outcome->name}» уже есть. Пропустите звонок или отметьте «это тоже {$outcome->name}».",
                'landmark' => new CallLandmarkResource($outcome->loadCount(['examples', 'calls'])),
            ], 409);
        }

        return $this->decided($outcome);
    }

    private function decided(CallRecording $call): JsonResponse
    {
        $call->load(['trimmer', 'moderator', 'landmark']);

        return response()->json([
            'data' => new CallResource($call),
            'landmark' => $call->landmark ? new CallLandmarkResource($call->landmark->loadCount(['examples', 'calls'])) : null,
        ]);
    }
}
