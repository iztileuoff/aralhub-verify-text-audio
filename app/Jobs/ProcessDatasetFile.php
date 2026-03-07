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

    public int $timeout = 600;

    public int $tries = 3;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function handle(): void
    {
        $file = storage_path('app/'.$this->path);

        $handle = fopen($file, 'r');

        $chunk = [];
        $size = 1000;

        while (($line = fgets($handle)) !== false) {

            $chunk[] = $line;

            if (count($chunk) == $size) {
                ProcessDatasetChunk::dispatch($chunk);
                $chunk = [];
            }
        }

        if (!empty($chunk)) {
            ProcessDatasetChunk::dispatch($chunk);
        }

        fclose($handle);
    }
}
