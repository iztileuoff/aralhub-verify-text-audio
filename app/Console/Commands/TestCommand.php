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
        Audio::cursor()->each(function ($audio) {
            if (!Storage::disk('public')->exists($audio->edit_converted_audio_filename)) {
                $this->info($audio->edit_audio_filename . PHP_EOL);
            }
        });

        $this->info('Success!');
    }
}
