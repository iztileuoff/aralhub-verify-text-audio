<?php

namespace App\Console\Commands;

use App\Models\Audio;
use Illuminate\Console\Command;

class UpdateDurationCommand extends Command
{
    protected $signature = 'update:duration {--filename=result.txt}';

    protected $description = 'Update converted audio durations from the Python script result file';

    public function handle(): void
    {
        $path = storage_path('app/'.$this->option('filename'));

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            [$filename, $duration] = explode(';', $line);

            $filenameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
            $audio = Audio::where('edit_audio_filename', 'like', "audio/{$filenameWithoutExt}.%")->first();

            if ($audio && $audio->edit_converted_audio_duration == null) {
                $audio->update([
                    'edit_converted_audio_duration' => (int) $duration,
                ]);
            } else {
                $this->info("Not found: {$filename}".PHP_EOL);
            }
        }

        $this->info('Audio updated successfully.');
    }
}
