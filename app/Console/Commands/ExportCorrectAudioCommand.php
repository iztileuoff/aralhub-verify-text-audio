<?php

namespace App\Console\Commands;

use App\Models\Audio;
use Illuminate\Console\Command;

class ExportCorrectAudioCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audio:export-correct {--filename=correct_audios.tsv}';

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
        $path = storage_path('app/private/' . $filename);

        // In Laravel 11/12 default storage is app/private or app/public. Lets just use app/
        $path = storage_path('app/' . $filename);

        $file = fopen($path, 'w');

        $audios = Audio::query()
            ->with('text')
            ->where('is_correct', true)
            ->lazy();

        $count = 0;

        foreach ($audios as $audio) {
            $clean = fn ($v) => str_replace('"', '', $v);

            $line = implode("\t", [
                $audio->text?->id,
                $audio->edit_converted_audio_filename,
                $clean($audio->text?->edit_original_transcript),
                $clean($audio->text?->edit_normalized_transcript),
                $clean($audio->text?->edit_tokenized_transcript),
                $audio->edit_converted_audio_duration,
                $audio->edit_speaker_gender->value,
            ]);

            fwrite($file, $line . PHP_EOL);

            $count++;
        }

        fclose($file);

        $this->info("Exported {$count} correct audios to {$path}.");

        return self::SUCCESS;
    }
}
