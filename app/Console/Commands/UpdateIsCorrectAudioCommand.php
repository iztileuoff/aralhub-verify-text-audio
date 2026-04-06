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
//        $editSpeakerId = $this->argument('user_id');
//
//        $date = now();
//        $moderatorId = 1;
//
//        $count = Audio::query()
//            ->where('edit_speaker_id', $editSpeakerId)
//            ->whereNull('is_correct')
//            ->update([
//                'is_correct' => true,
//                'moderator_id' => $moderatorId,
//                'moderator_started_at' => $date,
//                'moderator_finished_at' => $date,
//            ]);
//
//        $this->info("Successfully updated {$count} audio records for user ID: {$editSpeakerId}");

        $audios = Audio::query()
            ->whereNull('speak_started_at')
            ->whereNull('is_correct')
            ->get();

        foreach ($audios as $audio) {
            $textId = $audio->text_id;
            $editSpeakerId = $audio->edit_speaker_id;

            if (Audio::query()->where('text_id', $textId)->where('edit_speaker_id', $editSpeakerId)->count() > 1) {
                Audio::query()->where('text_id', $textId)->where('edit_speaker_id', $editSpeakerId)->whereNull('is_correct')->update(['is_correct' => false]);
            }
        }
    }
}
