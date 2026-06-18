<?php

namespace App\Console\Commands;

use App\Enums\RoleEnum;
use App\Models\Audio;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Rap2hpoutre\FastExcel\FastExcel;
use Rap2hpoutre\FastExcel\SheetCollection;

class ExportReportCommand extends Command
{
    private const int FILE_ID = 8;

    protected $signature = 'report:export {--filename=report.xlsx}';

    protected $description = 'Export speakers and moderators report to an Excel file';

    public function handle(): int
    {
        $filename = $this->option('filename');
        $path = storage_path('app/'.$filename);

        $sheets = new SheetCollection([
            'Speakers' => $this->buildSpeakersSheet(),
            'Moderators' => $this->buildModeratorsSheet(),
        ]);

        (new FastExcel($sheets))->export($path);

        $this->info("Exported report to {$path}.");

        return self::SUCCESS;
    }

    private function buildSpeakersSheet(): Collection
    {
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
                $correctMap[$row->edit_speaker_id] = (int) $row->total;
            } else {
                $incorrectMap[$row->edit_speaker_id] = (int) $row->total;
            }
        }

        $speakers = User::query()
            ->where('role', RoleEnum::SPEAKER->value)
            ->whereIn('id', $totalWritten->keys())
            ->orderBy('id')
            ->get(['id', 'first_name', 'last_name', 'phone', 'is_verified']);

        return $speakers->map(function (User $speaker) use ($totalWritten, $correctMap, $incorrectMap): array {
            $correct = $correctMap[$speaker->id] ?? 0;
            $incorrect = $incorrectMap[$speaker->id] ?? 0;

            return [
                'ID' => $speaker->id,
                'Full Name' => trim($speaker->first_name.' '.$speaker->last_name),
                'Phone' => $speaker->phone,
                'Verified' => $speaker->is_verified ? 'Yes' : 'No',
                'Written' => (int) ($totalWritten[$speaker->id] ?? 0),
                'Checked' => $correct + $incorrect,
                'Correct' => $correct,
                'Incorrect' => $incorrect,
            ];
        });
    }

    private function buildModeratorsSheet(): Collection
    {
        $totalChecked = Audio::query()
            ->selectRaw('moderator_id, is_correct, COUNT(*) as total')
            ->whereNotNull('moderator_finished_at')
            ->whereNotNull('is_correct')
            ->whereHas('text', fn (Builder $query) => $query->where('file_id', self::FILE_ID))
            ->groupBy('moderator_id', 'is_correct')
            ->get();

        $correctMap = [];
        $incorrectMap = [];

        foreach ($totalChecked as $row) {
            if ($row->is_correct) {
                $correctMap[$row->moderator_id] = (int) $row->total;
            } else {
                $incorrectMap[$row->moderator_id] = (int) $row->total;
            }
        }

        $moderatorIds = array_values(array_unique([
            ...array_keys($correctMap),
            ...array_keys($incorrectMap),
        ]));

        $moderators = User::query()
            ->where('role', RoleEnum::MODERATOR->value)
            ->whereIn('id', $moderatorIds)
            ->orderBy('id')
            ->get(['id', 'first_name', 'last_name', 'phone', 'is_verified']);

        return $moderators->map(function (User $moderator) use ($correctMap, $incorrectMap): array {
            $correct = $correctMap[$moderator->id] ?? 0;
            $incorrect = $incorrectMap[$moderator->id] ?? 0;

            return [
                'ID' => $moderator->id,
                'Full Name' => trim($moderator->first_name.' '.$moderator->last_name),
                'Phone' => $moderator->phone,
                'Verified' => $moderator->is_verified ? 'Yes' : 'No',
                'Checked' => $correct + $incorrect,
                'Correct' => $correct,
                'Incorrect' => $incorrect,
            ];
        });
    }
}
