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
//        $copied = 0;
//        $missing = 0;
//
//        Audio::where('is_correct', true)
//            ->cursor()
//            ->each(function ($audio) use (&$copied, &$missing) {
//                $source = $audio->edit_converted_audio_filename;
//                $destination = 'correct_audio/' . basename($source);
//
//                if (Storage::disk('public')->exists($source)) {
//                    Storage::disk('public')->copy($source, $destination);
//                    $copied++;
//                } else {
//                    $missing++;
//                }
//            });
//
//        $this->info("Copied: {$copied}");
//        $this->warn("Missing: {$missing}");
//        $this->info('Done!');

        $totalSamplesCount = Audio::query()
            ->where('is_correct', true)
            ->sum('edit_converted_audio_duration');

        $durationInSeconds = (int) ($totalSamplesCount / 16000);

        $hours = floor($durationInSeconds / 3600);
        $minutes = floor(($durationInSeconds % 3600) / 60);
        $seconds = $durationInSeconds % 60;

        $formatted = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

        $this->info("Total samples: {$totalSamplesCount}");
        $this->info("Duration: {$formatted}");
    }
}
