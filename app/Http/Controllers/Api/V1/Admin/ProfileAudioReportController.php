<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Audio;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileAudioReportController extends Controller
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
            ->selectRaw('DATE(speak_finished_at) as date, is_correct, COUNT(*) as total')
            ->where('edit_speaker_id', $userId)
            ->whereNotNull('speak_finished_at')
            ->whereBetween('speak_finished_at', [
                $fromDate.' 00:00:00',
                $toDate.' 23:59:59',
            ])
            ->groupBy('date', 'is_correct')
            ->get();

        $writtenMap = [];
        $correctMap = [];
        $incorrectMap = [];

        foreach ($stats as $row) {
            $writtenMap[$row->date] = ($writtenMap[$row->date] ?? 0) + (int) $row->total;

            if ($row->is_correct === null) {
                continue;
            }

            if ($row->is_correct) {
                $correctMap[$row->date] = (int) $row->total;
            } else {
                $incorrectMap[$row->date] = (int) $row->total;
            }
        }

        $byDate = [];
        $writtenCount = 0;
        $correctCount = 0;
        $incorrectCount = 0;

        foreach ($dates as $date) {
            $written = $writtenMap[$date] ?? 0;
            $correct = $correctMap[$date] ?? 0;
            $incorrect = $incorrectMap[$date] ?? 0;

            $byDate[] = [
                'date' => $date,
                'written_count' => $written,
                'checked_count' => $correct + $incorrect,
                'correct_count' => $correct,
                'incorrect_count' => $incorrect,
            ];

            $writtenCount += $written;
            $correctCount += $correct;
            $incorrectCount += $incorrect;
        }

        return response()->json([
            'data' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'written_count' => $writtenCount,
                'checked_count' => $correctCount + $incorrectCount,
                'correct_count' => $correctCount,
                'incorrect_count' => $incorrectCount,
                'by_date' => $byDate,
            ],
        ]);
    }
}
