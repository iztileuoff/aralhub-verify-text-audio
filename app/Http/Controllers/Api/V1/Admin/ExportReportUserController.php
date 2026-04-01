<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Audio;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenSpout\Common\Exception\InvalidArgumentException;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Common\Exception\UnsupportedTypeException;
use OpenSpout\Writer\Exception\WriterNotOpenedException;
use Rap2hpoutre\FastExcel\FastExcel;

class ExportReportUserController extends Controller
{
    /**
     * @throws IOException
     * @throws WriterNotOpenedException
     * @throws UnsupportedTypeException
     * @throws InvalidArgumentException
     */
    public function __invoke(Request $request)
    {
        $request->validate([
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d'],
            'admin_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', RoleEnum::ADMIN->value),
            ],
        ]);

        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $adminId = $request->input('admin_id');

        // ✅ Dates array (FIX for CarbonPeriod)
        $period = collect(CarbonPeriod::create($fromDate, $toDate))->toArray();

        // ✅ Get users
        $users = User::query()
            ->where('admin_id', $adminId)
            ->where('role', '!=', RoleEnum::SUPER_ADMIN->value)
            ->get();

        // ✅ Aggregated query (FAST & CORRECT)
        $audioStats = Audio::query()
            ->selectRaw('edit_speaker_id, DATE(speak_finished_at) as date, COUNT(*) as total')
            ->whereNotNull('speak_finished_at')
            ->whereBetween('speak_finished_at', [
                $fromDate . ' 00:00:00',
                $toDate . ' 23:59:59',
            ])
            ->groupBy('edit_speaker_id', 'date')
            ->get();

        // ✅ Build map: [user_id][date] => count
        $statsMap = [];
        foreach ($audioStats as $row) {
            $statsMap[$row->edit_speaker_id][$row->date] = $row->total;
        }

        // ✅ Export
        return (new FastExcel($users))->download('users-report.xlsx', function ($user) use ($period, $statsMap) {

            $data = [
                'ID' => $user->id,
                'Full Name' => $user->first_name . ' ' . $user->last_name,
                'Phone' => $user->phone,
            ];

            foreach ($period as $date) {
                $dateString = $date->format('Y-m-d');
                $data[$dateString] = $statsMap[$user->id][$dateString] ?? 0;
            }

            return $data;
        });
    }
}
