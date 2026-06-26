<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Audio;
use App\Models\User;
use Carbon\CarbonPeriod;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use OpenSpout\Common\Exception\InvalidArgumentException;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Common\Exception\UnsupportedTypeException;
use OpenSpout\Writer\Exception\WriterNotOpenedException;
use Rap2hpoutre\FastExcel\FastExcel;

#[Group(name: 'Reports & Exports', weight: 80)]
class ExportReportUserController extends Controller
{
    private const int FILE_ID = 8;

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
        ]);

        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $period = collect(CarbonPeriod::create($fromDate, $toDate))->toArray();

        // ✅ Aggregated stats
        $audioStats = Audio::query()
            ->selectRaw('edit_speaker_id, DATE(speak_finished_at) as date, COUNT(*) as total')
            ->whereNotNull('speak_finished_at')
            ->whereHas('text', fn (Builder $query) => $query->where('file_id', self::FILE_ID))
            ->whereBetween('speak_finished_at', [
                $fromDate.' 00:00:00',
                $toDate.' 23:59:59',
            ])
            ->groupBy('edit_speaker_id', 'date')
            ->get();

        // ✅ Maps
        $statsMap = [];
        $dayTotals = []; // total per day

        foreach ($audioStats as $row) {
            $statsMap[$row->edit_speaker_id][$row->date] = $row->total;

            // sum per day
            if (! isset($dayTotals[$row->date])) {
                $dayTotals[$row->date] = 0;
            }
            $dayTotals[$row->date] += $row->total;
        }

        // ensure all dates exist in dayTotals
        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $dayTotals[$dateString] = $dayTotals[$dateString] ?? 0;
        }

        // ✅ Users who spoke file_id=8 texts within the period
        $users = User::query()
            ->where('role', '!=', RoleEnum::SUPER_ADMIN->value)
            ->whereIn('id', array_keys($statsMap))
            ->get();

        // ✅ Prepare export rows
        $rows = [];

        foreach ($users as $user) {
            $row = [
                'ID' => $user->id,
                'Full Name' => $user->first_name.' '.$user->last_name,
                'Phone' => $user->phone,
                'Verified' => $user->is_verified ? 'Yes' : 'No',
            ];

            $userTotal = 0;

            foreach ($period as $date) {
                $dateString = $date->format('Y-m-d');
                $count = $statsMap[$user->id][$dateString] ?? 0;

                $row[$dateString] = $count;
                $userTotal += $count;
            }

            // ✅ total per user
            $row['TOTAL'] = $userTotal;

            $rows[] = $row;
        }

        // ✅ Add summary row (totals per day)
        $summaryRow = [
            'ID' => '',
            'Full Name' => 'TOTAL',
            'Phone' => '',
            'Verified' => '',
        ];

        $grandTotal = 0;

        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $summaryRow[$dateString] = $dayTotals[$dateString];
            $grandTotal += $dayTotals[$dateString];
        }

        $summaryRow['TOTAL'] = $grandTotal;

        $rows[] = $summaryRow;

        // ✅ Export
        return (new FastExcel(collect($rows)))->download('users-report.xlsx');
    }
}
