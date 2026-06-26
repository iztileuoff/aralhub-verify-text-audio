<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use OpenSpout\Common\Exception\InvalidArgumentException;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Common\Exception\UnsupportedTypeException;
use OpenSpout\Writer\Exception\WriterNotOpenedException;
use Rap2hpoutre\FastExcel\FastExcel;

#[Group(name: 'Reports & Exports', weight: 80)]
class ExportReportAllUserController extends Controller
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
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $date = $request->input('date');

        // ✅ Users
        $users = User::query()
            ->where('is_verified', true)
            ->withCount([
                'finishedSpeakAudio' => fn ($q) => $q->onDate('speak_finished_at', $date),
                'isCorrectTrueAudio' => fn ($q) => $q->onDate('speak_finished_at', $date),
                'isCorrectFalseAudio' => fn ($q) => $q->onDate('speak_finished_at', $date),
                'dateFinishedModerationAudio' => fn ($q) => $q->onDate('moderator_finished_at', $date),
            ])
            ->get();

        return (new FastExcel($users))->download('users-report.xlsx');
    }
}
