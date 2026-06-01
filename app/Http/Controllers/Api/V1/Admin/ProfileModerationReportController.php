<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Audio;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileModerationReportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ]);

        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $userId = auth()->id();

        $dates = collect(CarbonPeriod::create($fromDate, $toDate))
            ->map(fn ($date): string => $date->format('Y-m-d'))
            ->all();

        $stats = Audio::query()
            ->selectRaw('DATE(moderator_finished_at) as date, is_correct, COUNT(*) as total')
            ->where('moderator_id', $userId)
            ->whereNotNull('moderator_finished_at')
            ->whereNotNull('is_correct')
            ->whereBetween('moderator_finished_at', [
                $fromDate.' 00:00:00',
                $toDate.' 23:59:59',
            ])
            ->groupBy('date', 'is_correct')
            ->get();

        $correctMap = [];
        $incorrectMap = [];

        foreach ($stats as $row) {
            if ($row->is_correct) {
                $correctMap[$row->date] = (int) $row->total;
            } else {
                $incorrectMap[$row->date] = (int) $row->total;
            }
        }

        $byDate = [];
        $correctCount = 0;
        $incorrectCount = 0;

        foreach ($dates as $date) {
            $correct = $correctMap[$date] ?? 0;
            $incorrect = $incorrectMap[$date] ?? 0;

            $byDate[] = [
                'date' => $date,
                'checked_count' => $correct + $incorrect,
                'correct_count' => $correct,
                'incorrect_count' => $incorrect,
            ];

            $correctCount += $correct;
            $incorrectCount += $incorrect;
        }

        return response()->json([
            'data' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'checked_count' => $correctCount + $incorrectCount,
                'correct_count' => $correctCount,
                'incorrect_count' => $incorrectCount,
                'by_date' => $byDate,
            ],
        ]);
    }
}
