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
        $audios = Audio::query()
            ->whereDate('moderator_finished_at', '<=', '2026-04-07 19:00:00')
            ->get();

        $count = 0;

        foreach ($audios as $audio) {
            if ($audio->edit_audio_filename) {
                Storage::delete($audio->edit_audio_filename);
            }

            if ($audio->edit_converted_audio_filename) {
                Storage::delete($audio->edit_converted_audio_filename);
            }

            $count++;
        }

        $this->info("Done: deleted $count records.");
    }
}
