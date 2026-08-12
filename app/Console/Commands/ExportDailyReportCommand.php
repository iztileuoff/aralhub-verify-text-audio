<?php

namespace App\Console\Commands;

use App\Enums\RoleEnum;
use App\Models\Audio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Rap2hpoutre\FastExcel\FastExcel;
use Rap2hpoutre\FastExcel\SheetCollection;

class ExportDailyReportCommand extends Command
{
    protected $signature = 'report:daily
        {--filename=daily-report.xlsx : Output Excel filename}
        {--from=2026-06-20 : Inclusive start date of the daily breakdown (YYYY-MM-DD)}
        {--to= : Inclusive end date of the daily breakdown (YYYY-MM-DD); defaults to today}';

    protected $description = 'Export a day-by-day breakdown of speaker recordings and moderator checks to Excel';

    /** Inclusive first day of the report. */
    private Carbon $from;

    /** Inclusive last day of the report. */
    private Carbon $to;

    /**
     * Ordered list of calendar days (Y-m-d) covered by the report.
     *
     * @var list<string>
     */
    private array $days = [];

    /** Id of the dataset file the report is scoped to. */
    private int $mainFileId;

    public function handle(): int
    {
        if (! $this->resolveDateRange()) {
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

        $this->info("Exported daily report to {$path}.");

        return self::SUCCESS;
    }

    /**
     * Read and validate --from / --to, then materialise the day list.
     * Returns false (and prints an error) on malformed or inverted dates.
     */
    private function resolveDateRange(): bool
    {
        $from = $this->parseDate((string) $this->option('from'));

        if ($from === null) {
            $this->error('The --from option must be a valid date in YYYY-MM-DD format.');

            return false;
        }

        $toOption = $this->option('to');

        if ($toOption === null || $toOption === '') {
            $to = Carbon::today();
        } else {
            $to = $this->parseDate((string) $toOption);

            if ($to === null) {
                $this->error('The --to option must be a valid date in YYYY-MM-DD format.');

                return false;
            }
        }

        if ($to->lt($from)) {
            $this->error('The --to date must be on or after the --from date.');

            return false;
        }

        $this->from = $from;
        $this->to = $to;
        $this->days = [];

        for ($cursor = $from->copy(); $cursor->lte($to); $cursor->addDay()) {
            $this->days[] = $cursor->format('Y-m-d');
        }

        return true;
    }

    /**
     * Parse a strict YYYY-MM-DD date at the start of the day, or null if invalid.
     */
    private function parseDate(string $value): ?Carbon
    {
        try {
            $date = Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return $date->format('Y-m-d') === $value ? $date : null;
    }

    /**
     * Speakers sheet: one row per speaker, one column per day counting
     * finished recordings bucketed by the speak_finished_at date.
     */
    private function buildSpeakersSheet(): Collection
    {
        $rows = Audio::query()
            ->selectRaw('edit_speaker_id, DATE(speak_finished_at) as day, COUNT(*) as total')
            ->whereNotNull('edit_speaker_id')
            ->whereNotNull('speak_finished_at')
            ->where('speak_finished_at', '>=', $this->from)
            ->where('speak_finished_at', '<', $this->to->copy()->addDay())
            ->whereHas('text', fn (Builder $query) => $query->where('file_id', $this->mainFileId))
            ->groupBy('edit_speaker_id', 'day')
            ->get();

        return $this->buildSheet($rows, 'edit_speaker_id', RoleEnum::SPEAKER);
    }

    /**
     * Moderators sheet: one row per moderator, one column per day counting
     * finished checks bucketed by the moderator_finished_at date.
     */
    private function buildModeratorsSheet(): Collection
    {
        $rows = Audio::query()
            ->selectRaw('moderator_id, DATE(moderator_finished_at) as day, COUNT(*) as total')
            ->whereNotNull('moderator_id')
            ->whereNotNull('moderator_finished_at')
            ->where('moderator_finished_at', '>=', $this->from)
            ->where('moderator_finished_at', '<', $this->to->copy()->addDay())
            ->whereHas('text', fn (Builder $query) => $query->where('file_id', $this->mainFileId))
            ->groupBy('moderator_id', 'day')
            ->get();

        return $this->buildSheet($rows, 'moderator_id', RoleEnum::MODERATOR);
    }

    /**
     * Turn per-user/per-day aggregate rows into a matrix of users (rows)
     * against calendar days (columns) with a trailing total.
     *
     * @param  Collection<int, Model>  $rows
     */
    private function buildSheet(Collection $rows, string $userKey, RoleEnum $role): Collection
    {
        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row->{$userKey}][$row->day] = (int) $row->total;
        }

        $users = User::query()
            ->where('role', $role->value)
            ->whereIn('id', array_keys($counts))
            ->orderBy('id')
            ->get(['id', 'first_name', 'last_name', 'phone']);

        return $users->map(function (User $user) use ($counts): array {
            $perDay = $counts[$user->id] ?? [];

            $record = [
                'ID' => $user->id,
                'Full Name' => trim($user->first_name.' '.$user->last_name),
                'Phone' => $user->phone,
            ];

            $total = 0;

            foreach ($this->days as $day) {
                $count = $perDay[$day] ?? 0;
                $record[$day] = $count;
                $total += $count;
            }

            $record['Total'] = $total;

            return $record;
        });
    }
}
