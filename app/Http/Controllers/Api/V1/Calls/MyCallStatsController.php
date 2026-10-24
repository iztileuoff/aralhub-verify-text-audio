<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Enums\CallEventTypeEnum;
use App\Enums\CallStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Calls\MyCallStatsRequest;
use App\Models\Audio;
use App\Models\CallRecording;
use App\Models\CallRecordingEvent;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

#[Group(name: 'Calls - Trimming', weight: 60)]
class MyCallStatsController extends Controller
{
    /**
     * Моя статистика: за день (по умолчанию сегодня) и за всё время.
     */
    public function __invoke(MyCallStatsRequest $request): JsonResponse
    {
        $userId = $request->user()->id;
        $date = Carbon::parse($request->validated('date') ?? today());

        $day = CallRecordingEvent::tallyByTrimmer($date->copy()->startOfDay(), $date->copy()->endOfDay(), $userId)[$userId] ?? [];
        $total = CallRecordingEvent::tallyByTrimmer(trimmerId: $userId)[$userId] ?? [];

        $count = fn (array $tally, CallEventTypeEnum $type): int => $tally[$type->value]['count'] ?? 0;
        $seconds = fn (array $tally, CallEventTypeEnum $type): float => round(($tally[$type->value]['samples'] ?? 0) / Audio::SAMPLE_RATE, 2);

        return response()->json([
            'data' => [
                'date' => $date->toDateString(),
                'day' => [
                    'submitted' => $count($day, CallEventTypeEnum::SUBMITTED),
                    'approved' => $count($day, CallEventTypeEnum::APPROVED),
                    'rejected' => $count($day, CallEventTypeEnum::REJECTED),
                    'skipped' => $count($day, CallEventTypeEnum::SKIPPED),
                    'trimmed_duration_seconds' => $seconds($day, CallEventTypeEnum::SUBMITTED),
                ],
                'total' => [
                    'submitted' => $count($total, CallEventTypeEnum::SUBMITTED),
                    'approved' => $count($total, CallEventTypeEnum::APPROVED),
                    'rejected' => $count($total, CallEventTypeEnum::REJECTED),
                    'skipped' => $count($total, CallEventTypeEnum::SKIPPED),
                    'on_moderation' => CallRecording::query()
                        ->where('trimmer_id', $userId)
                        ->where('status', CallStatusEnum::SUBMITTED)
                        ->count(),
                    'approved_duration_seconds' => $seconds($total, CallEventTypeEnum::APPROVED),
                ],
            ],
        ]);
    }
}
