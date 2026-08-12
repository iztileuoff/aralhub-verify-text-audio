<?php

namespace App\Console\Commands;

use App\Models\Audio;
use App\Models\Export;
use App\Models\File;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class ExportCorrectAudioCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audio:export-correct
        {--filename=correct_audios.tsv : Output file under storage/app/}
        {--file-id= : Dataset file to export; defaults to the active dataset}
        {--name= : Export group name; defaults to "<dataset label> batch YYYY-MM"}
        {--all : Re-dump everything ready, ignoring exported_at}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export audio records where is_correct is true to a .tsv file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filename = $this->option('filename');
        $fileId = $this->resolveFileId();
        $all = (bool) $this->option('all');
        $path = storage_path('app/'.$filename);

        $file = fopen($path, 'w');

        $audios = Audio::query()
            ->with('text')
            ->where('is_correct', true)
            ->whereHas('text', fn (Builder $query) => $query->where('file_id', $fileId))
            ->whereNotNull('edit_converted_audio_duration')
            ->withinSttLimit()
            ->when(! $all, fn (Builder $query) => $query->whereNull('exported_at'))
            ->lazy();

        $count = 0;
        $exportedIds = [];

        foreach ($audios as $audio) {
            $clean = fn ($v) => str_replace('"', '', $v);

            $audioFilename = preg_replace('#^audio/#', '', $audio->edit_audio_filename);
            $audioFilename = preg_replace('/\.[^.\/]+$/', '', $audioFilename).'.wav';

            $line = implode("\t", [
                $audio->text?->id,
                $audioFilename,
                $clean($audio->text?->edit_original_transcript),
                $clean($audio->text?->edit_normalized_transcript),
                $clean($audio->text?->edit_tokenized_transcript),
                $audio->edit_converted_audio_duration,
                $audio->edit_speaker_gender->value,
            ]);

            fwrite($file, $line.PHP_EOL);

            $exportedIds[] = $audio->id;
            $count++;
        }

        fclose($file);

        $export = null;

        // A re-export (--all) is a raw dump; it must not touch the export tracking.
        if (! $all && $count > 0) {
            $export = Export::create([
                'name' => $this->resolveName($fileId),
                'filename' => $filename,
                'exported_count' => $count,
            ]);

            $this->markAsExported($exportedIds, $export->id);
        }

        $suffix = $export
            ? ' (export #'.$export->id.($export->name ? ": {$export->name}" : '').')'
            : '';
        $this->info("Exported {$count} correct audios to {$path}.{$suffix}");

        return self::SUCCESS;
    }

    /**
     * Dataset file to export: the --file-id option, or the active dataset when
     * the option is omitted. An explicit id still reaches an older dataset —
     * that is how the backlog of a previous dataset gets finished off.
     */
    private function resolveFileId(): int
    {
        $option = $this->option('file-id');

        return $option === null || $option === ''
            ? (int) config('dataset.main_file_id')
            : (int) $option;
    }

    /**
     * Name of the export group: the --name option, or the convention
     * "<dataset label> batch YYYY-MM" so that groups say which dataset and
     * which month they came from without anyone having to remember a flag.
     */
    private function resolveName(int $fileId): ?string
    {
        $name = $this->option('name');

        if ($name !== null && $name !== '') {
            return $name;
        }

        return File::query()->find($fileId)?->exportGroupName();
    }

    /**
     * Link the exported audios to the export and stamp them, in batches.
     *
     * @param  array<int, int>  $ids
     */
    private function markAsExported(array $ids, int $exportId): void
    {
        foreach (array_chunk($ids, 1000) as $chunk) {
            Audio::query()
                ->whereIn('id', $chunk)
                ->update([
                    'exported_at' => now(),
                    'export_id' => $exportId,
                ]);
        }
    }
}
