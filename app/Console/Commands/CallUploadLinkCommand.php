<?php

namespace App\Console\Commands;

use App\Models\CallRecording;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

class CallUploadLinkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calls:upload-link
        {batch : Folder for this delivery in the calls bucket, e.g. batch-1}
        {--days=7 : How many days the link works (1–30)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Print a signed, expiring link where the customer uploads phone calls (a folder or an archive) straight to the calls bucket';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $batch = (string) $this->argument('batch');
        $days = (int) $this->option('days');

        if (! preg_match('/^'.CallRecording::UPLOAD_BATCH_PATTERN.'$/', $batch)) {
            $this->error('Batch must be letters, digits, "-" or "_" (up to 50), e.g. batch-1.');

            return self::FAILURE;
        }

        if ($days < 1 || $days > 30) {
            $this->error('--days must be between 1 and 30.');

            return self::FAILURE;
        }

        $expiresAt = now()->addDays($days);

        // Relative signature: valid whatever scheme/host the proxy in front reports.
        $path = URL::temporarySignedRoute('calls.upload.page', $expiresAt, ['batch' => $batch], absolute: false);

        $this->line(rtrim((string) config('app.url'), '/').$path);
        $this->info("Works until {$expiresAt->toDateTimeString()} UTC. Files land in \"".CallRecording::sourcePrefix($batch).'/", archives in "'.CallRecording::archivePrefix($batch).'/".');

        return self::SUCCESS;
    }
}
