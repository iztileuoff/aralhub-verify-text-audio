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
            $path = $audio->edit_audio_filename;

            if (!Storage::disk('public')->exists($path)) {
                $this->warn("File not found: $path");
            } else {
                Storage::disk('public')->delete($path);
                $this->info("Deleted: $path");
            }

            $path = $audio->edit_converted_audio_filename;

            if (!Storage::disk('public')->exists($path)) {
                $this->warn("File not found: $path");
            } else {
                Storage::disk('public')->delete($path);
                $this->info("Deleted: $path");
            }

            $count++;
        }

        $this->info("Done: deleted $count records.");
    }
}
