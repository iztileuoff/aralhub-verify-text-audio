<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Audio;
use App\Models\User;
use Carbon\CarbonPeriod;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Reports & Exports', weight: 80)]
class ReportSpeakerController extends Controller
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

        $periodWritten = Audio::query()
            ->selectRaw('edit_speaker_id, DATE(speak_finished_at) as date, COUNT(*) as total')
            ->whereNotNull('speak_finished_at')
            ->whereHas('text', fn (Builder $query) => $query->where('file_id', self::FILE_ID))
            ->whereBetween('speak_finished_at', [
                $fromDate.' 00:00:00',
                $toDate.' 23:59:59',
            ])
            ->groupBy('edit_speaker_id', 'date')
            ->get();

        $periodWrittenMap = [];

        foreach ($periodWritten as $row) {
            $periodWrittenMap[$row->edit_speaker_id][$row->date] = (int) $row->total;
        }

        $totalWritten = Audio::query()
            ->selectRaw('edit_speaker_id, COUNT(*) as total')
            ->whereNotNull('speak_finished_at')
            ->whereHas('text', fn (Builder $query) => $query->where('file_id', self::FILE_ID))
            ->groupBy('edit_speaker_id')
            ->pluck('total', 'edit_speaker_id');

        $totalChecked = Audio::query()
            ->selectRaw('edit_speaker_id, is_correct, COUNT(*) as total')
            ->whereNotNull('speak_finished_at')
            ->whereNotNull('is_correct')
            ->whereHas('text', fn (Builder $query) => $query->where('file_id', self::FILE_ID))
            ->groupBy('edit_speaker_id', 'is_correct')
            ->get();

        $correctMap = [];
        $incorrectMap = [];

        foreach ($totalChecked as $row) {
            if ($row->is_correct) {
                $correctMap[$row->edit_speaker_id] = $row->total;
            } else {
                $incorrectMap[$row->edit_speaker_id] = $row->total;
            }
        }

        $speakers = User::query()
            ->where('role', RoleEnum::SPEAKER->value)
            ->whereIn('id', $totalWritten->keys())
            ->orderBy('id')
            ->get(['id', 'first_name', 'last_name', 'phone', 'is_verified']);

        $data = $speakers->map(function (User $speaker) use ($dates, $periodWrittenMap, $totalWritten, $correctMap, $incorrectMap): array {
            $correct = $correctMap[$speaker->id] ?? 0;
            $incorrect = $incorrectMap[$speaker->id] ?? 0;

            $byDate = [];
            $periodTotal = 0;

            foreach ($dates as $date) {
                $count = $periodWrittenMap[$speaker->id][$date] ?? 0;
                $byDate[] = [
                    'date' => $date,
                    'written_count' => $count,
                ];
                $periodTotal += $count;
            }

            return [
                'id' => $speaker->id,
                'full_name' => trim($speaker->first_name.' '.$speaker->last_name),
                'phone' => $speaker->phone,
                'is_verified' => $speaker->is_verified,
                'period' => [
                    'written_count' => $periodTotal,
                    'by_date' => $byDate,
                ],
                'total' => [
                    'written_count' => (int) ($totalWritten[$speaker->id] ?? 0),
                    'checked_count' => $correct + $incorrect,
                    'correct_count' => $correct,
                    'incorrect_count' => $incorrect,
                ],
            ];
        });

        return response()->json([
            'data' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'speakers' => $data,
            ],
        ]);
    }
}
