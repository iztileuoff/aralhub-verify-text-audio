<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Audio;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportModeratorController extends Controller
{
    private const int FILE_ID = 8;

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ]);

        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $dates = collect(CarbonPeriod::create($fromDate, $toDate))
            ->map(fn ($date): string => $date->format('Y-m-d'))
            ->all();

        $periodChecked = Audio::query()
            ->selectRaw('moderator_id, DATE(moderator_finished_at) as date, is_correct, COUNT(*) as total')
            ->whereNotNull('moderator_finished_at')
            ->whereNotNull('is_correct')
            ->whereHas('text', fn (Builder $query) => $query->where('file_id', self::FILE_ID))
            ->whereBetween('moderator_finished_at', [
                $fromDate.' 00:00:00',
                $toDate.' 23:59:59',
            ])
            ->groupBy('moderator_id', 'date', 'is_correct')
            ->get();

        $totalChecked = Audio::query()
            ->selectRaw('moderator_id, is_correct, COUNT(*) as total')
            ->whereNotNull('moderator_finished_at')
            ->whereNotNull('is_correct')
            ->whereHas('text', fn (Builder $query) => $query->where('file_id', self::FILE_ID))
            ->groupBy('moderator_id', 'is_correct')
            ->get();

        $periodCorrect = [];
        $periodIncorrect = [];
        $totalCorrect = [];
        $totalIncorrect = [];

        foreach ($periodChecked as $row) {
            if ($row->is_correct) {
                $periodCorrect[$row->moderator_id][$row->date] = (int) $row->total;
            } else {
                $periodIncorrect[$row->moderator_id][$row->date] = (int) $row->total;
            }
        }

        foreach ($totalChecked as $row) {
            if ($row->is_correct) {
                $totalCorrect[$row->moderator_id] = $row->total;
            } else {
                $totalIncorrect[$row->moderator_id] = $row->total;
            }
        }

        $moderatorIds = array_values(array_unique([
            ...array_keys($totalCorrect),
            ...array_keys($totalIncorrect),
        ]));

        $moderators = User::query()
            ->where('role', RoleEnum::MODERATOR->value)
            ->whereIn('id', $moderatorIds)
            ->orderBy('id')
            ->get(['id', 'first_name', 'last_name', 'phone', 'is_verified']);

        $data = $moderators->map(function (User $moderator) use ($dates, $periodCorrect, $periodIncorrect, $totalCorrect, $totalIncorrect): array {
            $totalCorrectCount = $totalCorrect[$moderator->id] ?? 0;
            $totalIncorrectCount = $totalIncorrect[$moderator->id] ?? 0;

            $byDate = [];
            $periodCorrectCount = 0;
            $periodIncorrectCount = 0;

            foreach ($dates as $date) {
                $correct = $periodCorrect[$moderator->id][$date] ?? 0;
                $incorrect = $periodIncorrect[$moderator->id][$date] ?? 0;
                $byDate[] = [
                    'date' => $date,
                    'checked_count' => $correct + $incorrect,
                    'correct_count' => $correct,
                    'incorrect_count' => $incorrect,
                ];
                $periodCorrectCount += $correct;
                $periodIncorrectCount += $incorrect;
            }

            return [
                'id' => $moderator->id,
                'full_name' => trim($moderator->first_name.' '.$moderator->last_name),
                'phone' => $moderator->phone,
                'is_verified' => $moderator->is_verified,
                'period' => [
                    'checked_count' => $periodCorrectCount + $periodIncorrectCount,
                    'correct_count' => $periodCorrectCount,
                    'incorrect_count' => $periodIncorrectCount,
                    'by_date' => $byDate,
                ],
                'total' => [
                    'checked_count' => $totalCorrectCount + $totalIncorrectCount,
                    'correct_count' => $totalCorrectCount,
                    'incorrect_count' => $totalIncorrectCount,
                ],
            ];
        });

        return response()->json([
            'data' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'moderators' => $data,
            ],
        ]);
    }
}
