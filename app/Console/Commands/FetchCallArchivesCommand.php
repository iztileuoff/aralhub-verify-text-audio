<?php

namespace App\Console\Commands;

use App\Models\CallRecording;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileAttributes;

class FetchCallArchivesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calls:fetch-archives
        {batch : The delivery whose archives to download}
        {--to= : Local folder; defaults to storage/app/calls-import/<batch>}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download the archives uploaded through the customer link to the server, to unpack them there';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $batch = (string) $this->argument('batch');
        $target = rtrim($this->option('to') ?: storage_path('app/calls-import/'.$batch), '/\\');
        $disk = Storage::disk(CallRecording::disk());
        $prefix = CallRecording::archivePrefix($batch);

        if (! is_dir($target) && ! mkdir($target, 0755, true) && ! is_dir($target)) {
            $this->error("Cannot create {$target}.");

            return self::FAILURE;
        }

        $fetched = 0;

        foreach ($disk->getDriver()->listContents($prefix, true) as $item) {
            if (! $item instanceof FileAttributes) {
                continue;
            }

            $local = $target.'/'.basename($item->path());

            if (is_file($local) && filesize($local) === $item->fileSize()) {
                $this->line('already here: '.basename($local));

                continue;
            }

            $this->line('downloading '.basename($local).' ('.round(($item->fileSize() ?? 0) / 1048576).' MB)…');

            $in = $disk->readStream($item->path());
            $out = fopen($local.'.part', 'wb');

            if ($in === null || $out === false) {
                $this->error('Cannot read '.$item->path().' or write '.$local.'.');

                return self::FAILURE;
            }

            stream_copy_to_stream($in, $out);
            fclose($out);
            rename($local.'.part', $local);

            $fetched++;
        }

        $this->info("Downloaded {$fetched} archive file(s) to {$target}. Unpack them, then: php artisan calls:upload <folder> --batch={$batch}");

        return self::SUCCESS;
    }
}
