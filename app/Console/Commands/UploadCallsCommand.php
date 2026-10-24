<?php

namespace App\Console\Commands;

use App\Models\CallRecording;
use FilesystemIterator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileAttributes;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class UploadCallsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calls:upload
        {dir : Local folder with the calls (e.g. an unpacked archive)}
        {--batch= : Delivery name; the files go to source/<batch>/ in the calls bucket}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Upload a local folder of phone calls to the calls bucket and register them (idempotent)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dir = rtrim(str_replace('\\', '/', (string) $this->argument('dir')), '/');
        $batch = (string) $this->option('batch');

        if (! is_dir($dir)) {
            $this->error("No such folder: {$dir}.");

            return self::FAILURE;
        }

        if (! preg_match('/^'.CallRecording::UPLOAD_BATCH_PATTERN.'$/', $batch)) {
            $this->error('--batch is required: letters, digits, "-" or "_", e.g. batch-1.');

            return self::FAILURE;
        }

        $disk = Storage::disk(CallRecording::disk());
        $prefix = CallRecording::sourcePrefix($batch);
        $existing = [];

        foreach ($disk->getDriver()->listContents($prefix, true) as $item) {
            if ($item instanceof FileAttributes) {
                $existing[$item->path()] = $item->fileSize();
            }
        }

        $files = iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)), false);
        $audio = array_filter($files, fn (SplFileInfo $file): bool => $file->isFile()
            && in_array(strtolower($file->getExtension()), CallRecording::UPLOAD_AUDIO_EXTENSIONS, true));

        $uploaded = 0;
        $skipped = 0;
        $failed = 0;
        $bar = $this->output->createProgressBar(count($audio));

        foreach ($audio as $file) {
            $relative = ltrim(substr(str_replace('\\', '/', $file->getPathname()), strlen($dir)), '/');
            $key = $prefix.'/'.$relative;

            if (($existing[$key] ?? null) === $file->getSize()) {
                $skipped++;
            } else {
                $stream = fopen($file->getPathname(), 'rb');

                if ($stream !== false && $disk->writeStream($key, $stream)) {
                    $uploaded++;
                } else {
                    $failed++;
                    $this->newLine();
                    $this->warn("Failed: {$relative}");
                }

                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Uploaded {$uploaded}, already there {$skipped}, failed {$failed}; other files ignored: ".(count($files) - count($audio)).'.');

        $this->call('calls:register', ['--prefix' => $prefix, '--batch' => $batch]);

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
