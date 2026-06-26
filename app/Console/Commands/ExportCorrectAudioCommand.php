<?php

namespace App\Console\Commands;

use App\Models\Audio;
use App\Models\Export;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class ExportCorrectAudioCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audio:export-correct {--filename=correct_audios.tsv} {--file-id=8} {--name=} {--all}';

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
        $fileId = (int) $this->option('file-id');
        $all = (bool) $this->option('all');
        $path = storage_path('app/'.$filename);

        $file = fopen($path, 'w');

        $audios = Audio::query()
            ->with('text')
            ->where('is_correct', true)
            ->whereHas('text', fn (Builder $query) => $query->where('file_id', $fileId))
            ->whereNotNull('edit_converted_audio_duration')
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
                'name' => $this->option('name'),
                'filename' => $filename,
                'exported_count' => $count,
            ]);

            $this->markAsExported($exportedIds, $export->id);
        }

        $suffix = $export ? " (export #{$export->id})" : '';
        $this->info("Exported {$count} correct audios to {$path}.{$suffix}");

        return self::SUCCESS;
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
