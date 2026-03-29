<?php

namespace App\Console\Commands;

use App\Enums\GenderEnum;
use App\Models\Action;
use App\Models\Audio;
use App\Models\Text;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestCommand extends Command
{
    protected $signature = 'test';

    protected $description = 'Command description';

    public function handle(): void
    {
        $totalSamplesCount = Audio::query()
            ->where('is_correct', true)
            ->whereDate('moderator_finished_at', '>', '2026-03-26 13:00:00')
            ->sum('edit_converted_audio_duration');

        $count = Audio::query()
            ->where('is_correct', true)
            ->whereDate('moderator_finished_at', '>', '2026-03-26 13:00:00')
            ->count();

        $durationInSeconds = (int) ($totalSamplesCount / 16000);

        $hours = floor($durationInSeconds / 3600);
        $minutes = floor(($durationInSeconds % 3600) / 60);
        $seconds = $durationInSeconds % 60;

        $formatted = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

        $this->info("Count: $count");
        $this->info("Total samples: {$totalSamplesCount}");
        $this->info("Duration: {$formatted}");
    }
}
