<?php

namespace App\Console\Commands;

use App\Enums\RoleEnum;
use App\Models\Audio;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Rap2hpoutre\FastExcel\FastExcel;
use Rap2hpoutre\FastExcel\SheetCollection;

class ExportReportCommand extends Command
{
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
        $speakers = User::query()
            ->where('role', RoleEnum::SPEAKER->value)
            ->orderBy('id')
            ->get(['id', 'first_name', 'last_name', 'phone']);

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
                $correctMap[$row->edit_speaker_id] = (int) $row->total;
            } else {
                $incorrectMap[$row->edit_speaker_id] = (int) $row->total;
            }
        }

        return $speakers->map(function (User $speaker) use ($totalWritten, $correctMap, $incorrectMap): array {
            $correct = $correctMap[$speaker->id] ?? 0;
            $incorrect = $incorrectMap[$speaker->id] ?? 0;

            return [
                'ID' => $speaker->id,
                'Full Name' => trim($speaker->first_name.' '.$speaker->last_name),
                'Phone' => $speaker->phone,
                'Written' => (int) ($totalWritten[$speaker->id] ?? 0),
                'Checked' => $correct + $incorrect,
                'Correct' => $correct,
                'Incorrect' => $incorrect,
            ];
        });
    }

    private function buildModeratorsSheet(): Collection
    {
        $moderators = User::query()
            ->where('role', RoleEnum::MODERATOR->value)
            ->orderBy('id')
            ->get(['id', 'first_name', 'last_name', 'phone']);

        $totalChecked = Audio::query()
            ->selectRaw('moderator_id, is_correct, COUNT(*) as total')
            ->whereNotNull('moderator_finished_at')
            ->whereNotNull('is_correct')
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

        return $moderators->map(function (User $moderator) use ($correctMap, $incorrectMap): array {
            $correct = $correctMap[$moderator->id] ?? 0;
            $incorrect = $incorrectMap[$moderator->id] ?? 0;

            return [
                'ID' => $moderator->id,
                'Full Name' => trim($moderator->first_name.' '.$moderator->last_name),
                'Phone' => $moderator->phone,
                'Checked' => $correct + $incorrect,
                'Correct' => $correct,
                'Incorrect' => $incorrect,
            ];
        });
    }
}
