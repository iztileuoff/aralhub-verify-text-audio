<?php

namespace App\Console\Commands;

use App\Models\Audio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DeleteAudioCommand extends Command
{
    protected $signature = 'audio:delete {user_id}';

    protected $description = 'Delete a user\'s audio files and records, then update audio_count on related texts';

    public function handle(): void
    {
        $userId = (int) $this->argument('user_id');

        $audios = Audio::query()
            ->where('edit_speaker_id', $userId)
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

        $this->info("Done: deleted $count records for user $userId.");
    }
}
