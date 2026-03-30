<?php

namespace App\Console\Commands;

use App\Models\Audio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DeleteIncorrectAudioCommand extends Command
{
    protected $signature = 'audio:delete-incorrect';

    protected $description = 'Delete incorrect audio files and update counts';

    public function handle(): void
    {
        $audios = Audio::query()
            ->where('edit_speaker_id', 259)
            ->whereIn('text_id', [5294, 1125, 1019, 5961])
            ->get();

        $count = 0;

        foreach ($audios as $audio) {
            if ($audio->edit_audio_filename) {
                Storage::delete($audio->edit_audio_filename);
            }

            if ($audio->edit_converted_audio_filename) {
                Storage::delete($audio->edit_converted_audio_filename);
            }

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
