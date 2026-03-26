<?php

namespace App\Console\Commands;

use App\Models\Audio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CopyFilesCommand extends Command
{
    protected $signature = 'copy:files';

    protected $description = 'Command description';

    public function handle(): void
    {
        $copied = 0;
        $missing = 0;

        Audio::where('is_correct', true)
            ->whereDate('moderator_finished_at', '>', '2026-03-24 13:00:00')
            ->cursor()
            ->each(function ($audio) use (&$copied, &$missing) {
                $source = $audio->edit_converted_audio_filename;
                $destination = 'correct_audio/' . basename($source);

                if (Storage::disk('public')->exists($source)) {
                    Storage::disk('public')->copy($source, $destination);
                    $copied++;
                } else {
                    $missing++;
                }
            });

        $this->info("Copied: {$copied}");
        $this->warn("Missing: {$missing}");
        $this->info('Done!');
    }
}
