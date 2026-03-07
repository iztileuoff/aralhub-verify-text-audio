<?php

namespace App\Jobs;

use App\Models\Text;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDatasetFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function handle(): void
    {
        $filePath = Storage::path($this->path);

        $handle = fopen($filePath, 'r');

        while (($line = fgets($handle)) !== false) {

            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = explode("\t", $line);

            if (count($parts) < 4) {
                continue;
            }

            $audioFilename = trim($parts[0]);
            $filterOriginalTranscript = trim($parts[1]);
            $filterNormalizedTranscript = trim($parts[2]);
            $filterTokenizedTranscript = trim($parts[3]);

            Text::where('audio_filename', $audioFilename)->update([
                'filter_original_transcript' => $filterOriginalTranscript,
                'filter_normalized_transcript' => $filterNormalizedTranscript,
                'filter_tokenized_transcript' => $filterTokenizedTranscript
            ]);
        }

        fclose($handle);
    }
}
