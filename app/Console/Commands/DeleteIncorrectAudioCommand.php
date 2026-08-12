<?php

namespace App\Console\Commands;

use App\Models\Audio;
use Illuminate\Console\Command;

class DeleteIncorrectAudioCommand extends Command
{
    protected $signature = 'audio:delete-incorrect';

    protected $description = 'Delete incorrect audio files and update counts';

    public function handle(): void
    {
        $audios = Audio::query()
            ->where('is_correct', false)
            ->get();

        $count = 0;

        foreach ($audios as $audio) {
            $audio->deleteStoredFiles();

            $text = $audio->text;

            $audio->delete();

            if ($text) {
                $text->update([
                    'audio_count' => $text->audio()->count(),
                ]);
            }

            $count++;
        }

        $this->info("Done: deleted $count records.");
    }
}
