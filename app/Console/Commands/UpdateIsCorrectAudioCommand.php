<?php

namespace App\Console\Commands;

use App\Models\Audio;
use App\Models\Text;
use Illuminate\Console\Command;

class UpdateIsCorrectAudioCommand extends Command
{
    protected $signature = 'update:is-correct-audio {user_id}';

    protected $description = 'Command description';

    public function handle(): void
    {
        $editSpeakerId = $this->argument('user_id');

        $date = now();
        $moderatorId = 1;

        $count = Audio::query()
            ->where('edit_speaker_id', $editSpeakerId)
            ->whereNull('is_correct')
            ->update([
                'is_correct' => true,
                'moderator_id' => $moderatorId,
                'moderator_started_at' => $date,
                'moderator_finished_at' => $date,
            ]);

        $this->info("Successfully updated {$count} audio records for user ID: {$editSpeakerId}");
    }
}
