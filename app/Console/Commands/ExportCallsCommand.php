<?php

namespace App\Console\Commands;

use App\Enums\CallStatusEnum;
use App\Models\CallRecording;
use App\Models\Export;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class ExportCallsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calls:export
        {--filename=calls.tsv : Output file under storage/app/}
        {--name= : Export group name; defaults to "calls batch YYYY-MM"}
        {--all : Re-dump every approved call, ignoring exported_at}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export approved phone calls to a .tsv file in the dataset format, plus a list of their files';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filename = $this->option('filename');
        $all = (bool) $this->option('all');
        $path = storage_path('app/'.$filename);
        $filesPath = preg_replace('/\.[^.\/]+$/', '', $path).'_files.txt';

        $tsv = @fopen($path, 'w');
        $list = @fopen($filesPath, 'w');

        if ($tsv === false || $list === false) {
            $this->error('Cannot write to '.($tsv === false ? $path : $filesPath).'.');

            return self::FAILURE;
        }

        $calls = CallRecording::query()
            ->where('status', CallStatusEnum::APPROVED)
            ->whereNotNull('trimmed_path')
            ->when(! $all, fn (Builder $query): Builder => $query->whereNull('exported_at'))
            ->lazyById();

        $count = 0;
        $exportedIds = [];

        foreach ($calls as $call) {
            // Quotes are dropped like in audio:export-correct; the whitespace they leave
            // behind (and any tab or line break) is folded so the row stays one clean line.
            $clean = fn (?string $value): string => trim((string) preg_replace('/\s+/u', ' ', str_replace('"', '', (string) $value)));
            $id = 'call-'.$call->id;

            // Same columns as audio:export-correct; the id is prefixed so it can
            // never collide with a text_id when both files are merged, and the
            // gender column stays empty — a call has two voices.
            fwrite($tsv, implode("\t", [
                $id,
                basename($call->trimmed_path),
                $clean($call->transcript),
                $clean($call->normalized_transcript),
                $clean($call->tokenized_transcript),
                $call->trimmed_duration,
                '',
            ]).PHP_EOL);

            fwrite($list, $id.';'.$call->trimmed_path.PHP_EOL);

            $exportedIds[] = $call->id;
            $count++;
        }

        fclose($tsv);
        fclose($list);

        $export = null;

        // A re-export (--all) is a raw dump; it must not touch the export tracking.
        if (! $all && $count > 0) {
            $export = Export::create([
                'name' => $this->option('name') ?: 'calls batch '.now()->format('Y-m'),
                'filename' => $filename,
                'exported_count' => $count,
            ]);

            foreach (array_chunk($exportedIds, 1000) as $chunk) {
                CallRecording::query()->whereIn('id', $chunk)->update([
                    'exported_at' => now(),
                    'export_id' => $export->id,
                ]);
            }
        }

        $suffix = $export ? " (export #{$export->id}: {$export->name})" : '';
        $this->info("Exported {$count} approved calls to {$path}, file list in {$filesPath}.{$suffix}");

        return self::SUCCESS;
    }
}
