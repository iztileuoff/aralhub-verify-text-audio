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
    protected $signature = 'audio:export-filenames
        {--filename=audio_filenames.txt : Output file under storage/app/}
        {--file-id= : Dataset file to export; defaults to the active dataset}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export "id;filename" pairs of correct audios that still need a converted duration for the Python script';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filename = $this->option('filename');
        $fileId = $this->resolveFileId();
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

            fwrite($file, $audio->id.';'.$audioFilename.PHP_EOL);

            $count++;
        }

        fclose($file);

        $this->info("Exported {$count} filenames to {$path}.");

        return self::SUCCESS;
    }

    /**
     * Dataset file to export: the --file-id option, or the active dataset when
     * the option is omitted. An explicit id still reaches an older dataset.
     */
    private function resolveFileId(): int
    {
        $option = $this->option('file-id');

        return $option === null || $option === ''
            ? (int) config('dataset.main_file_id')
            : (int) $option;
    }
}
