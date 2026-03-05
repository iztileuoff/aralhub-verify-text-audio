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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SendTsvFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const TRANSLATE_ENDPOINT = 'https://api.translator.aralhub.uz/translate-dataset';

    private const EXPECTED_COLS = 7;

    private const CHUNK_SIZE = 500;

    public int $timeout = 120;

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
        $path = Storage::disk('local')->path($this->file->path);

        if (! file_exists($path)) {
            $this->markFailed("TSV file not found on disk: {$path}");

            return;
        }

        $this->file->update(['status' => 'sending']);

        Log::info("SendTsvFile: sending file #{$this->file->id} to translation API");

        // ── 1. Send file ──────────────────────────────────────────────────────
        $response = Http::timeout(60 * 10)
            ->attach(
                'file',
                fopen($path, 'r'),
                $this->file->filename,
                ['Content-Type' => $this->file->mime_type]
            )
            ->post(self::TRANSLATE_ENDPOINT);

        if ($response->failed()) {
            $error = "Translation API error [{$response->status()}]: ".$response->body();
            $this->markFailed($error);

            Log::error("SendTsvFile: failed for file #{$this->file->id}", [
                'http_status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            throw new \RuntimeException($error);
        }

        // ── 2. Parse response and update texts rows ───────────────────────────
        $rawBody = $response->body(); // plain-text TSV string

        $updated = $this->parseAndUpdate($rawBody);

        // ── 3. Mark done ──────────────────────────────────────────────────────
        $this->file->update([
            'status' => 'sent',
        ]);

        Log::info("SendTsvFile: file #{$this->file->id} translation applied", [
            'texts_updated' => $updated,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->markFailed($exception->getMessage());
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * Parse TSV response body and update filter_* columns on matching Text rows.
     *
     * Response columns:
     *   [0] transcript_id
     *   [1] audio_filename
     *   [2] filter_original_transcript   (translated original)
     *   [3] filter_normalized_transcript (translated normalized)
     *   [4] filter_tokenized_transcript  (translated tokenized)
     *   [5] duration
     *   [6] speaker_gender
     *
     * @throws \Throwable
     */
    private function parseAndUpdate(string $body): int
    {
        $lines = explode("\n", $body);
        $buffer = [];   // audio_filename => [filter fields]
        $count = 0;
        $skipped = 0;

        foreach ($lines as $line) {
            $line = rtrim($line, "\r");

            if (empty($line)) {
                continue;
            }

            $cols = explode("\t", $line);

            if (count($cols) < self::EXPECTED_COLS) {
                Log::warning('SendTsvFile: skipping malformed response row', [
                    'file_id' => $this->file->id,
                    'cols' => count($cols),
                    'preview' => mb_substr($line, 0, 80),
                ]);
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
                $speakerGender,
            ] = array_map('trim', $cols);

            $buffer[] = [
                'audio_filename' => $audioFilename,
                'filter_original_transcript' => $filterOriginal,
                'filter_normalized_transcript' => $filterNormalized,
                'filter_tokenized_transcript' => $filterTokenized,
            ];

            $count++;

            // Flush chunk
            if (count($buffer) >= self::CHUNK_SIZE) {
                $this->flushUpdates($buffer);
                $buffer = [];
            }
        }

        if (! empty($buffer)) {
            $this->flushUpdates($buffer);
        }

        if ($skipped > 0) {
            Log::warning("SendTsvFile: skipped {$skipped} malformed rows", [
                'file_id' => $this->file->id,
            ]);
        }

        return $count;
    }

    /**
     * Bulk-update texts rows using a single CASE WHEN query per chunk.
     *
     * @throws \Throwable
     */
    private function flushUpdates(array $rows): void
    {
        $filenames = array_column($rows, 'audio_filename');

        // Index by filename for fast lookup
        $map = array_column($rows, null, 'audio_filename');

        DB::transaction(function () use ($filenames, $map) {
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
        });
    }

    private function markFailed(string $reason): void
    {
        $this->file->update([
            'status' => 'failed',
            'error_message' => $reason,
        ]);
    }
}
