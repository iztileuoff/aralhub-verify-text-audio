<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExportReportUserController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d'],
            'admin_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', RoleEnum::ADMIN->value)],
        ]);

        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $adminId = $request->input('admin_id');

        $period = \Carbon\CarbonPeriod::create($fromDate, $toDate);
        $counts = [];
        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $counts['dateFinishedSpeakAudio as count_' . str_replace('-', '_', $dateString)] = fn ($q) => $q->whereDate('speak_finished_at', $dateString);
        }

        $users = \App\Models\User::query()
            ->where('admin_id', $adminId)
            ->where('role', '!=', RoleEnum::SUPER_ADMIN->value)
            ->withCount($counts)
            ->get();

        return (new \Rap2hpoutre\FastExcel\FastExcel($users))->download('users-report.xlsx', function ($user) use ($period) {
            $data = [
                'ID' => $user->id,
                'Full Name' => $user->first_name . ' ' . $user->last_name,
                'Phone' => $user->phone,
            ];

            foreach ($period as $date) {
                $dateString = $date->format('Y-m-d');
                $key = 'count_' . str_replace('-', '_', $dateString) . '_count';
                $data[$dateString] = $user->$key ?? 0;
            }

            return $data;
        });
    }
}
