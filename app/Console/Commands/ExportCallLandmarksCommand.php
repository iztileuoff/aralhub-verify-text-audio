<?php

namespace App\Console\Commands;

use App\Models\CallLandmark;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class ExportCallLandmarksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calls:export-landmarks
        {--filename=landmarks.csv : Output file under storage/app/}
        {--after-id= : Only landmarks with a larger id — for sending just the new ones}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export the call landmarks with how many calls named them, as a text,mentions CSV';

    /**
     * Phone numbers must not leave the system: seven or more digits, spaces,
     * dashes, brackets and "+" between them allowed.
     */
    private const PHONE_PATTERN = '/(?:\d[\s\-()+]*){7,}/u';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = storage_path('app/'.$this->option('filename'));
        $afterId = $this->option('after-id');

        $csv = @fopen($path, 'w');

        if ($csv === false) {
            $this->error("Cannot write to {$path}.");

            return self::FAILURE;
        }

        $landmarks = CallLandmark::query()
            ->when($afterId !== null, fn (Builder $query): Builder => $query->where('id', '>', (int) $afterId))
            ->withCount('calls')
            ->orderByDesc('calls_count')
            ->orderBy('id')
            ->get(['id', 'name']);

        fputcsv($csv, ['text', 'mentions'], escape: '');

        $exported = 0;
        $mentions = 0;
        $skipped = [];

        foreach ($landmarks as $landmark) {
            $text = trim($landmark->name);

            if ($text === '' || mb_strlen($text) > 100 || preg_match(self::PHONE_PATTERN, $text)) {
                $skipped[] = "#{$landmark->id} {$landmark->name}";

                continue;
            }

            // A landmark always came from a call; if that call is gone it still was named once.
            $count = max(1, $landmark->calls_count);

            fputcsv($csv, [$text, $count], escape: '');

            $exported++;
            $mentions += $count;
        }

        fclose($csv);

        $this->info("Exported {$exported} landmarks ({$mentions} mentions) to {$path}.");

        if ($skipped !== []) {
            $this->warn('Left out '.count($skipped).' (empty, over 100 characters or looks like a phone number):');

            foreach ($skipped as $line) {
                $this->line('  '.$line);
            }
        }

        if ($landmarks->isNotEmpty()) {
            $this->line('Next time, to send only new landmarks: --after-id='.$landmarks->max('id'));
        }

        return self::SUCCESS;
    }
}
