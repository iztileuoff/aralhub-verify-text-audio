<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenSpout\Common\Exception\InvalidArgumentException;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Common\Exception\UnsupportedTypeException;
use OpenSpout\Writer\Exception\WriterNotOpenedException;
use Rap2hpoutre\FastExcel\FastExcel;

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
            'admin_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', RoleEnum::ADMIN->value),
            ],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $adminId = $request->input('admin_id');
        $date = $request->input('date');

        // ✅ Users
        $users = User::query()
            ->where('admin_id', $adminId)
            ->withCount([
                'finishedSpeakAudio' => fn ($q) => $q->whereDate('speak_finished_at', $date),
                'isCorrectTrueAudio' => fn ($q) => $q->whereDate('speak_finished_at', $date),
                'isCorrectFalseAudio' => fn ($q) => $q->whereDate('speak_finished_at', $date),
                'dateFinishedModerationAudio' => fn ($q) => $q->whereDate('moderator_finished_at', $date),
            ])
            ->get();

        return (new FastExcel($users))->download('users-report.xlsx');
    }
}
