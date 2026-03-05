<?php

namespace App\Jobs;

use App\Models\File;
use App\Models\Text;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SendTsvFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const TRANSLATE_ENDPOINT = 'https://api.translator.aralhub.uz/translate-dataset';

    private const EXPECTED_COLS = 7;
    private const CHUNK_SIZE = 500;

    public int $timeout = 1800;
    public int $tries = 3;

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function __construct(private readonly File $file) {}

    /**
     * @throws \Throwable
     * @throws ConnectionException
     */
    public function handle(): void
    {
        $path = Storage::disk('public')->path($this->file->path);

        if (!file_exists($path)) {
            $this->markFailed("TSV file not found: {$path}");
            return;
        }

        $this->file->update(['status' => 'sending']);

        Log::info("SendTsvFile: sending file #{$this->file->id}");

        $translatedPath = storage_path("app/tmp/translated_{$this->file->id}.tsv");

        if (!is_dir(dirname($translatedPath))) {
            mkdir(dirname($translatedPath), 0777, true);
        }

        /*
        |--------------------------------------------------------------------------
        | Send file and stream response directly to disk
        |--------------------------------------------------------------------------
        */

        $response = retry(3, function () use ($path, $translatedPath) {

            return Http::timeout(1800)
                ->sink($translatedPath)
                ->attach(
                    'file',
                    file_get_contents($path),
                    basename($path)
                )
                ->post(self::TRANSLATE_ENDPOINT);

        }, 5000);

        if ($response->failed()) {

            $error = "Translation API error [{$response->status()}]: ".$response->body();

            $this->markFailed($error);

            Log::error("SendTsvFile failed", [
                'file_id' => $this->file->id,
                'status' => $response->status(),
            ]);

            throw new \RuntimeException($error);
        }

        /*
        |--------------------------------------------------------------------------
        | Parse TSV file from disk
        |--------------------------------------------------------------------------
        */

        $updated = $this->parseAndUpdateFile($translatedPath);

        $this->file->update([
            'status' => 'sent'
        ]);

        Log::info("SendTsvFile completed", [
            'file_id' => $this->file->id,
            'texts_updated' => $updated
        ]);

        unlink($translatedPath);
    }

    public function failed(\Throwable $exception): void
    {
        $this->markFailed($exception->getMessage());
    }

    /*
    |--------------------------------------------------------------------------
    | Parse TSV file line-by-line
    |--------------------------------------------------------------------------
    */

    private function parseAndUpdateFile(string $filePath): int
    {
        $handle = fopen($filePath, 'r');

        $buffer = [];
        $count = 0;
        $skipped = 0;

        while (($line = fgets($handle)) !== false) {

            $line = rtrim($line, "\r\n");

            if ($line === '') {
                continue;
            }

            $cols = explode("\t", $line);

            if (count($cols) < self::EXPECTED_COLS) {
                $skipped++;
                continue;
            }

            [
                $transcriptId,
                $audioFilename,
                $filterOriginal,
                $filterNormalized,
                $filterTokenized,
                $duration,
                $speakerGender
            ] = array_map('trim', $cols);

            $buffer[] = [
                'audio_filename' => $audioFilename,
                'filter_original_transcript' => $filterOriginal,
                'filter_normalized_transcript' => $filterNormalized,
                'filter_tokenized_transcript' => $filterTokenized,
            ];

            $count++;

            if (count($buffer) >= self::CHUNK_SIZE) {
                $this->flushUpdates($buffer);
                $buffer = [];
            }
        }

        fclose($handle);

        if (!empty($buffer)) {
            $this->flushUpdates($buffer);
        }

        if ($skipped > 0) {
            Log::warning("SendTsvFile skipped rows", [
                'file_id' => $this->file->id,
                'skipped' => $skipped
            ]);
        }

        return $count;
    }

    /*
    |--------------------------------------------------------------------------
    | Batch update
    |--------------------------------------------------------------------------
    */

    private function flushUpdates(array $rows): void
    {
        $filenames = array_column($rows, 'audio_filename');

        $map = array_column($rows, null, 'audio_filename');

        Text::where('file_id', $this->file->id)
            ->whereIn('audio_filename', $filenames)
            ->each(function (Text $text) use ($map) {

                $data = $map[$text->audio_filename] ?? null;

                if ($data) {
                    $text->update([
                        'filter_original_transcript' => $data['filter_original_transcript'],
                        'filter_normalized_transcript' => $data['filter_normalized_transcript'],
                        'filter_tokenized_transcript' => $data['filter_tokenized_transcript'],
                    ]);
                }
            });
    }

    private function markFailed(string $reason): void
    {
        $this->file->update([
            'status' => 'failed',
            'error_message' => $reason
        ]);
    }
}
