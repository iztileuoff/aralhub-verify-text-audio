<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Audio;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportModeratorController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ]);

        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $moderators = User::query()
            ->where('role', RoleEnum::MODERATOR->value)
            ->orderBy('id')
            ->get(['id', 'first_name', 'last_name', 'phone']);

        $periodChecked = Audio::query()
            ->selectRaw('moderator_id, is_correct, COUNT(*) as total')
            ->whereNotNull('moderator_finished_at')
            ->whereNotNull('is_correct')
            ->whereBetween('moderator_finished_at', [
                $fromDate.' 00:00:00',
                $toDate.' 23:59:59',
            ])
            ->groupBy('moderator_id', 'is_correct')
            ->get();

        $totalChecked = Audio::query()
            ->selectRaw('moderator_id, is_correct, COUNT(*) as total')
            ->whereNotNull('moderator_finished_at')
            ->whereNotNull('is_correct')
            ->groupBy('moderator_id', 'is_correct')
            ->get();

        $periodCorrect = [];
        $periodIncorrect = [];
        $totalCorrect = [];
        $totalIncorrect = [];

        foreach ($periodChecked as $row) {
            if ($row->is_correct) {
                $periodCorrect[$row->moderator_id] = $row->total;
            } else {
                $periodIncorrect[$row->moderator_id] = $row->total;
            }
        }

        foreach ($totalChecked as $row) {
            if ($row->is_correct) {
                $totalCorrect[$row->moderator_id] = $row->total;
            } else {
                $totalIncorrect[$row->moderator_id] = $row->total;
            }
        }

        $data = $moderators->map(function (User $moderator) use ($periodCorrect, $periodIncorrect, $totalCorrect, $totalIncorrect): array {
            $periodCorrectCount = $periodCorrect[$moderator->id] ?? 0;
            $periodIncorrectCount = $periodIncorrect[$moderator->id] ?? 0;
            $totalCorrectCount = $totalCorrect[$moderator->id] ?? 0;
            $totalIncorrectCount = $totalIncorrect[$moderator->id] ?? 0;

            return [
                'id' => $moderator->id,
                'full_name' => trim($moderator->first_name.' '.$moderator->last_name),
                'phone' => $moderator->phone,
                'period' => [
                    'checked_count' => $periodCorrectCount + $periodIncorrectCount,
                    'correct_count' => $periodCorrectCount,
                    'incorrect_count' => $periodIncorrectCount,
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
