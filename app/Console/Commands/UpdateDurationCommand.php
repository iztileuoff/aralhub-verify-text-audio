<?php

namespace App\Console\Commands;

use App\Models\Audio;
use Illuminate\Console\Command;

class UpdateDurationCommand extends Command
{
    protected $signature = 'update:duration {--filename=result.txt}';

    protected $description = 'Update converted audio durations from the Python script result file ("id;duration" per line)';

    public function handle(): void
    {
        $path = storage_path('app/'.$this->option('filename'));

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            [$id, $duration] = explode(';', trim($line));

            $audio = Audio::find((int) $id);

            if ($audio && $audio->edit_converted_audio_duration === null) {
                $audio->update([
                    'edit_converted_audio_duration' => (int) $duration,
                ]);
            } else {
                $this->info("Skipped (not found or already set): {$id}".PHP_EOL);
            }
        }

        $this->info('Audio updated successfully.');
    }
}
