<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Enums\CallEventTypeEnum;
use App\Enums\CallStatusEnum;
use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Calls\CallStatsRequest;
use App\Models\Audio;
use App\Models\CallRecording;
use App\Models\CallRecordingEvent;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

#[Group(name: 'Calls - Moderation', weight: 61)]
class CallStatsController extends Controller
{
    /**
     * Статистика: состояние всей очереди и работа каждого обрезчика за период.
     *
     * `totals` — текущее состояние, от периода не зависит. `trimmers` — события за
     * период (по умолчанию сегодня); обрезчики без событий тоже в списке, с нулями.
     */
    public function __invoke(CallStatsRequest $request): JsonResponse
    {
        $from = Carbon::parse($request->validated('from') ?? today())->startOfDay();
        $to = Carbon::parse($request->validated('to') ?? $from)->endOfDay();

        return response()->json([
            'data' => [
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'totals' => $this->totals(),
                'trimmers' => $this->trimmers($from, $to),
            ],
        ]);
    }

    /**
     * @return array<string, int|float>
     */
    private function totals(): array
    {
        $byStatus = CallRecording::query()
            ->selectRaw('status, count(*) as calls_count')
            ->groupBy('status')
            ->pluck('calls_count', 'status');

        $totals = ['files_total' => (int) $byStatus->sum()];

        foreach (CallStatusEnum::cases() as $status) {
            $totals[$status->value] = (int) ($byStatus[$status->value] ?? 0);
        }

        $approvedSamples = (int) CallRecording::query()
            ->where('status', CallStatusEnum::APPROVED)
            ->sum('trimmed_duration');

        return [
            ...$totals,
            'approved_duration_seconds' => round($approvedSamples / Audio::SAMPLE_RATE, 2),
            'exported' => CallRecording::query()->whereNotNull('exported_at')->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function trimmers(Carbon $from, Carbon $to): array
    {
        $tally = CallRecordingEvent::tallyByTrimmer($from, $to);

        // Last time each trimmer did something themselves (not a moderator on their work).
        $lastActivity = CallRecordingEvent::query()
            ->whereColumn('user_id', 'trimmer_id')
            ->selectRaw('trimmer_id, max(created_at) as last_at')
            ->groupBy('trimmer_id')
            ->pluck('last_at', 'trimmer_id');

        $users = User::query()
            ->where('role', RoleEnum::TRIMMER->value)
            ->orWhereIn('id', array_keys($tally))
            ->orderBy('first_name')
            ->orderBy('id')
            ->get(['id', 'first_name', 'last_name']);

        return $users->map(function (User $user) use ($tally, $lastActivity): array {
            $events = $tally[$user->id] ?? [];
            $count = fn (CallEventTypeEnum $type): int => $events[$type->value]['count'] ?? 0;
            $seconds = fn (CallEventTypeEnum $type): float => round(($events[$type->value]['samples'] ?? 0) / Audio::SAMPLE_RATE, 2);
            $lastAt = $lastActivity[$user->id] ?? null;

            return [
                'user' => ['id' => $user->id, 'first_name' => $user->first_name, 'last_name' => $user->last_name],
                'submitted' => $count(CallEventTypeEnum::SUBMITTED),
                'approved' => $count(CallEventTypeEnum::APPROVED),
                'rejected' => $count(CallEventTypeEnum::REJECTED),
                'skipped' => $count(CallEventTypeEnum::SKIPPED),
                'trimmed_duration_seconds' => $seconds(CallEventTypeEnum::SUBMITTED),
                'approved_duration_seconds' => $seconds(CallEventTypeEnum::APPROVED),
                'last_activity_at' => $lastAt ? Carbon::parse($lastAt)->toIso8601String() : null,
            ];
        })->values()->all();
    }
}
