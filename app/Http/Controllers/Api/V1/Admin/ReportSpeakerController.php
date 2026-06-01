<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Audio;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportSpeakerController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ]);

        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $speakers = User::query()
            ->where('role', RoleEnum::SPEAKER->value)
            ->orderBy('id')
            ->get(['id', 'first_name', 'last_name', 'phone']);

        $periodWritten = Audio::query()
            ->selectRaw('edit_speaker_id, COUNT(*) as total')
            ->whereNotNull('speak_finished_at')
            ->whereBetween('speak_finished_at', [
                $fromDate.' 00:00:00',
                $toDate.' 23:59:59',
            ])
            ->groupBy('edit_speaker_id')
            ->pluck('total', 'edit_speaker_id');

        $totalWritten = Audio::query()
            ->selectRaw('edit_speaker_id, COUNT(*) as total')
            ->whereNotNull('speak_finished_at')
            ->groupBy('edit_speaker_id')
            ->pluck('total', 'edit_speaker_id');

        $totalChecked = Audio::query()
            ->selectRaw('edit_speaker_id, is_correct, COUNT(*) as total')
            ->whereNotNull('speak_finished_at')
            ->whereNotNull('is_correct')
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

        $data = $speakers->map(function (User $speaker) use ($periodWritten, $totalWritten, $correctMap, $incorrectMap): array {
            $correct = $correctMap[$speaker->id] ?? 0;
            $incorrect = $incorrectMap[$speaker->id] ?? 0;

            return [
                'id' => $speaker->id,
                'full_name' => trim($speaker->first_name.' '.$speaker->last_name),
                'phone' => $speaker->phone,
                'period' => [
                    'written_count' => (int) ($periodWritten[$speaker->id] ?? 0),
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
