<?php

namespace App\Console\Commands;

use App\Models\Audio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MoveConvertedFileCommand extends Command
{
    protected $signature = 'move:converted-file';

    protected $description = 'Command description';

    public function handle(): void
    {
        $disk = Storage::disk('public');

        $sourcePath = 'output_wav';
        $targetPath = 'audio_conv';
        $resultFile = storage_path('app/public/processed_results.txt');

        // counters
        $total = 0;
        $moved = 0;
        $notFoundInDb = 0;
        $missingFile = 0;

        if (! file_exists($resultFile)) {
            $this->error('processed_results.txt not found');

            return;
        }

        $lines = file($resultFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $total++;

            [$filename, $duration] = explode(';', $line);

            $this->line("Processing: {$filename}");

            $audio = Audio::query()
                ->where('edit_audio_filename', "audio/{$filename}")
                ->first();

            if (! $audio) {
                $notFoundInDb++;
                $this->warn("Audio not found in DB: {$filename}");

                continue;
            }

            $sourceFile = "{$sourcePath}/{$filename}";
            $targetFile = "{$targetPath}/{$filename}";

            if (! $disk->exists($sourceFile)) {
                $missingFile++;
                $this->warn("File missing: {$filename}");

                continue;
            }

            $disk->move($sourceFile, $targetFile);

            $audio->update([
                'edit_converted_audio_filename' => $targetFile,
                'edit_converted_audio_duration' => (int) $duration,
            ]);

            $moved++;
            $this->info("Moved: {$filename}");
        }

        // summary
        $this->newLine();
        $this->info('===== SUMMARY =====');
        $this->info("Total lines: {$total}");
        $this->info("Moved: {$moved}");
        $this->warn("Not found in DB: {$notFoundInDb}");
        $this->warn("Missing files: {$missingFile}");
    }
}
