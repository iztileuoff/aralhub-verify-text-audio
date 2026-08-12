<?php

namespace App\Console\Commands;

use App\Enums\RoleEnum;
use App\Models\Audio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Rap2hpoutre\FastExcel\FastExcel;
use Rap2hpoutre\FastExcel\SheetCollection;

class ExportReportCommand extends Command
{
    protected $signature = 'report:export
        {--filename=report.xlsx : Output Excel filename}
        {--exported=all : Export status filter — all, 1 (exported only), or 0 (not exported only)}
        {--registered-from= : Include only users registered (created_at) on/after this date (YYYY-MM-DD)}';

    protected $description = 'Export speakers and moderators report to an Excel file';

    /** Export status filter: true = exported only, false = not exported only, null = all. */
    private ?bool $exportedOnly = null;

    /** Inclusive lower bound on user registration date (users.created_at); null = no bound. */
    private ?Carbon $registeredFrom = null;

    /** Id of the dataset file the report is scoped to. */
    private int $mainFileId;

    public function handle(): int
    {
        if (! $this->resolveExportedFilter() || ! $this->resolveRegisteredFilter()) {
            return self::INVALID;
        }

        $this->mainFileId = (int) config('dataset.main_file_id');

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

    /**
     * Read and validate the --exported option into $exportedOnly.
     * Returns false (and prints an error) on an invalid value.
     */
    private function resolveExportedFilter(): bool
    {
        $value = (string) $this->option('exported');

        if (! in_array($value, ['all', '1', '0'], true)) {
            $this->error('The --exported option must be one of: all, 1, 0.');

            return false;
        }

        $this->exportedOnly = match ($value) {
            '1' => true,
            '0' => false,
            default => null,
        };

        return true;
    }

    /**
     * Read and validate the --registered-from option into $registeredFrom.
     * Returns false (and prints an error) on a malformed date.
     */
    private function resolveRegisteredFilter(): bool
    {
        $this->registeredFrom = null;

        $value = $this->option('registered-from');

        if ($value === null || $value === '') {
            return true;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            $date = null;
        }

        if ($date === null || $date->format('Y-m-d') !== $value) {
            $this->error('The --registered-from option must be a valid date in YYYY-MM-DD format.');

            return false;
        }

        $this->registeredFrom = $date;

        return true;
    }

    /**
     * Constrain a query to the selected export status, if any.
     */
    private function applyExportedFilter(Builder $query): Builder
    {
        return match ($this->exportedOnly) {
            true => $query->whereNotNull('exported_at'),
            false => $query->whereNull('exported_at'),
            default => $query,
        };
    }

    /**
     * Constrain a user query to those registered on/after $registeredFrom, if set.
     */
    private function applyRegisteredFilter(Builder $query): Builder
    {
        return $this->registeredFrom
            ? $query->where('created_at', '>=', $this->registeredFrom)
            : $query;
    }

    private function buildSpeakersSheet(): Collection
    {
        $totalWritten = $this->applyExportedFilter(
            Audio::query()
                ->selectRaw('edit_speaker_id, COUNT(*) as total')
                ->whereNotNull('speak_finished_at')
                ->whereHas('text', fn (Builder $query) => $query->where('file_id', $this->mainFileId))
        )
            ->groupBy('edit_speaker_id')
            ->pluck('total', 'edit_speaker_id');

        $totalChecked = $this->applyExportedFilter(
            Audio::query()
                ->selectRaw('edit_speaker_id, is_correct, COUNT(*) as total')
                ->whereNotNull('speak_finished_at')
                ->whereNotNull('is_correct')
                ->whereHas('text', fn (Builder $query) => $query->where('file_id', $this->mainFileId))
        )
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

        $speakers = $this->applyRegisteredFilter(
            User::query()
                ->where('role', RoleEnum::SPEAKER->value)
                ->whereIn('id', $totalWritten->keys())
        )
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
        $totalChecked = $this->applyExportedFilter(
            Audio::query()
                ->selectRaw('moderator_id, is_correct, COUNT(*) as total')
                ->whereNotNull('moderator_finished_at')
                ->whereNotNull('is_correct')
                ->whereHas('text', fn (Builder $query) => $query->where('file_id', $this->mainFileId))
        )
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

        $moderators = $this->applyRegisteredFilter(
            User::query()
                ->where('role', RoleEnum::MODERATOR->value)
                ->whereIn('id', $moderatorIds)
        )
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
