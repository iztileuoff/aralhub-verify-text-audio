<?php

namespace App\Jobs;

use App\Models\Text;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDatasetChunk implements ShouldQueue
{
    use Dispatchable, \Illuminate\Bus\Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(public $lines)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach ($this->lines as $line) {

            $line = trim($line);
            if (! $line) {
                continue;
            }

            $parts = explode("\t", $line);
            if (count($parts) < 4) {
                continue;
            }

            $audioFilename = trim($parts[0]);

            Text::where('audio_filename', $audioFilename)->update([
                'filter_original_transcript' => trim($parts[1]),
                'filter_normalized_transcript' => trim($parts[2]),
                'filter_tokenized_transcript' => trim($parts[3]),
            ]);
        }
    }
}
