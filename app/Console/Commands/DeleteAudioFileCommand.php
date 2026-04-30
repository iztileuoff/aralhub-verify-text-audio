<?php

namespace App\Console\Commands;

use App\Models\Audio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DeleteAudioFileCommand extends Command
{
    protected $signature = 'audio:delete-files';

    protected $description = 'Delete audio files';

    public function handle(): void
    {
        $count = 0;

        Audio::query()
            ->where('moderator_finished_at', '<=', '2026-04-07 19:00:00')
            ->chunk(500, function ($audios) use (&$count) {

                foreach ($audios as $audio) {
                    foreach ([
                        $audio->edit_audio_filename,
                        $audio->edit_converted_audio_filename,
                    ] as $file) {

                        if (empty($file)) {
                            continue;
                        }

                        if (Storage::disk('public')->exists($file)) {
                            Storage::disk('public')->delete($file);
                            $count++;
                        }
                    }
                }
            });

        $this->info("Total deleted files: $count");
    }
}
