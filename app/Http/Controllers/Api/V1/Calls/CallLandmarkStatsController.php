<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Enums\CallLandmarkDecisionEnum;
use App\Http\Controllers\Controller;
use App\Models\CallLandmark;
use App\Models\CallRecording;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group(name: 'Calls - Landmarks', weight: 62)]
class CallLandmarkStatsController extends Controller
{
    /**
     * Сводка очереди ориентиров: сколько звонков в каком статусе и размер словаря.
     */
    public function __invoke(): JsonResponse
    {
        $decided = CallRecording::query()
            ->whereNotNull('landmark_decision')
            ->selectRaw('landmark_decision, count(*) as calls_count')
            ->groupBy('landmark_decision')
            ->pluck('calls_count', 'landmark_decision');

        $undecided = CallRecording::query()->landmarkPending()->count();
        $inProgress = CallRecording::query()
            ->landmarkPending()
            ->whereNotNull('landmark_assigned_to')
            ->where('landmark_assigned_at', '>=', CallRecording::lockExpiredBefore())
            ->count();

        $counts = ['pending' => $undecided - $inProgress, 'in_progress' => $inProgress];

        foreach (CallLandmarkDecisionEnum::cases() as $decision) {
            $counts[$decision->value] = (int) ($decided[$decision->value] ?? 0);
        }

        return response()->json([
            'data' => [
                'calls_total' => array_sum($counts),
                ...$counts,
                'landmarks_total' => CallLandmark::query()->count(),
            ],
        ]);
    }
}
