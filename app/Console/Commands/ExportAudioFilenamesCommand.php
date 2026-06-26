<?php

namespace App\Console\Commands;

use App\Models\Audio;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class ExportAudioFilenamesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audio:export-filenames {--filename=audio_filenames.txt} {--file-id=8}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export filenames of correct audios that still need a converted duration for the Python script';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filename = $this->option('filename');
        $fileId = (int) $this->option('file-id');
        $path = storage_path('app/'.$filename);

        $file = fopen($path, 'w');

        $audios = Audio::query()
            ->where('is_correct', true)
            ->whereNull('edit_converted_audio_duration')
            ->whereHas('text', fn (Builder $query) => $query->where('file_id', $fileId))
            ->lazy();

        $count = 0;

        foreach ($audios as $audio) {
            $audioFilename = preg_replace('#^audio/#', '', $audio->edit_audio_filename);

            fwrite($file, $audioFilename.PHP_EOL);

            $count++;
        }

        fclose($file);

        $this->info("Exported {$count} filenames to {$path}.");

        return self::SUCCESS;
    }
}
