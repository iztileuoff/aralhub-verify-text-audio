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
        ]);

        $adminId = $request->input('admin_id');

        // ✅ Users
        $users = User::query()
            ->where('admin_id', $adminId)
            ->where('role', RoleEnum::SPEAKER->value)
            ->withCount(['finishedSpeakAudio', 'isCorrectTrueAudio', 'isCorrectFalseAudio'])
            ->get();

        return (new FastExcel($users))->download('users-report.xlsx');
    }
}
