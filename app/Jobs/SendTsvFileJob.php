<?php

namespace App\Jobs;

use App\Models\File;
use App\Models\Text;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SendTsvFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const TRANSLATE_ENDPOINT = 'https://api.translator.aralhub.uz/translate-dataset';
    private const EXPECTED_COLS      = 7;
    private const CHUNK_SIZE         = 500;

    public int $timeout = 300;
    public int $tries   = 3;

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function __construct(private readonly File $file) {}

    public function handle(): void
    {
        $path = Storage::disk('public')->path($this->file->path);

        if (!file_exists($path)) {
            $this->markFailed("TSV file not found on disk: {$path}");
            return;
        }

        $this->file->update(['status' => 'sending']);

        Log::info("SendTsvFile: sending file #{$this->file->id} to translation API");

        // ── 1. Send via raw cURL to avoid Guzzle cURL error 18 ───────────────
        // Error 18 = server closes connection before Content-Length is reached.
        // Fix: CURLOPT_IGNORE_CONTENT_LENGTH + read response into a temp stream.
        $responseBody = $this->sendWithCurl($path);

        if ($responseBody === null) {
            // markFailed already called inside sendWithCurl
            return;
        }

        // ── 2. Parse response and update texts rows ───────────────────────────
        $updated = $this->parseAndUpdate($responseBody);

        // ── 3. Mark done ──────────────────────────────────────────────────────
        $this->file->update([
            'status'             => 'sent',
        ]);

        Log::info("SendTsvFile: file #{$this->file->id} translation applied", [
            'texts_updated' => $updated,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->markFailed($exception->getMessage());
    }

    // ── Private: HTTP ─────────────────────────────────────────────────────────

    /**
     * Send the TSV file using raw cURL.
     *
     * Key options that fix cURL error 18:
     *   CURLOPT_IGNORE_CONTENT_LENGTH — ignore a mismatched Content-Length header
     *   CURLOPT_HTTP_VERSION CURL_HTTP_VERSION_1_1 — avoid HTTP/2 framing issues
     *   Writing to a temp file — avoids memory overflow on large responses
     */
    private function sendWithCurl(string $filePath): ?string
    {
        $tmpFile = tmpfile();
        $tmpPath = stream_get_meta_data($tmpFile)['uri'];

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => self::TRANSLATE_ENDPOINT,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'file' => new \CURLFile(
                    $filePath,
                    $this->file->mime_type,
                    $this->file->filename
                ),
            ],
            // ── Fixes for error 18 ────────────────────────────────────────
            CURLOPT_IGNORE_CONTENT_LENGTH => true,
            CURLOPT_HTTP_VERSION          => CURL_HTTP_VERSION_1_1,
            // ── Write response to temp file (handles large bodies) ────────
            CURLOPT_FILE               => $tmpFile,
            CURLOPT_FOLLOWLOCATION     => true,
            CURLOPT_TIMEOUT            => 240,
            CURLOPT_CONNECTTIMEOUT     => 30,
            CURLOPT_SSL_VERIFYPEER     => true,
            CURLOPT_SSL_VERIFYHOST     => 2,
        ]);

        $success  = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        $curlErrNo = curl_errno($curl);
        curl_close($curl);

        // cURL error 18 means partial data — still try to use what we got
        if (!$success && $curlErrNo !== CURLE_PARTIAL_FILE) {
            fclose($tmpFile);
            $this->markFailed("cURL error {$curlErrNo}: {$curlError}");
            Log::error("SendTsvFile: cURL failed for file #{$this->file->id}", [
                'curl_errno' => $curlErrNo,
                'curl_error' => $curlError,
            ]);
            return null;
        }

        if ($httpCode >= 400) {
            fclose($tmpFile);
            $this->markFailed("Translation API HTTP {$httpCode}");
            Log::error("SendTsvFile: HTTP error for file #{$this->file->id}", [
                'http_code' => $httpCode,
            ]);
            return null;
        }

        // Read response from temp file
        fseek($tmpFile, 0);
        $body = stream_get_contents($tmpFile);
        fclose($tmpFile);

        Log::info("SendTsvFile: received response for file #{$this->file->id}", [
            'http_code'     => $httpCode,
            'response_size' => strlen($body),
            'curl_errno'    => $curlErrNo, // 18 = partial but usable
        ]);

        return $body;
    }

    // ── Private: Parser ───────────────────────────────────────────────────────

    /**
     * Parse TSV response body and update filter_* columns on matching Text rows.
     *
     * Response columns:
     *   [0] transcript_id
     *   [1] audio_filename
     *   [2] filter_original_transcript
     *   [3] filter_normalized_transcript
     *   [4] filter_tokenized_transcript
     *   [5] duration
     *   [6] speaker_gender
     */
    private function parseAndUpdate(string $body): int
    {
        $lines   = explode("\n", $body);
        $buffer  = [];
        $count   = 0;
        $skipped = 0;

        foreach ($lines as $line) {
            $line = rtrim($line, "\r");

            if (empty($line)) {
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
            ] = array_map('trim', $cols);

            $buffer[$audioFilename] = [
                'filter_original_transcript'   => $filterOriginal,
                'filter_normalized_transcript' => $filterNormalized,
                'filter_tokenized_transcript'  => $filterTokenized,
            ];

            $count++;

            if (count($buffer) >= self::CHUNK_SIZE) {
                $this->flushUpdates($buffer);
                $buffer = [];
            }
        }

        if (!empty($buffer)) {
            $this->flushUpdates($buffer);
        }

        if ($skipped > 0) {
            Log::warning("SendTsvFile: skipped {$skipped} malformed rows in response", [
                'file_id' => $this->file->id,
            ]);
        }

        return $count;
    }

    /**
     * Bulk-update texts using whereIn on audio_filename.
     */
    private function flushUpdates(array $map): void
    {
        $filenames = array_keys($map);

        DB::transaction(function () use ($filenames, $map) {
            Text::where('file_id', $this->file->id)
                ->whereIn('audio_filename', $filenames)
                ->each(function (Text $text) use ($map) {
                    if (isset($map[$text->audio_filename])) {
                        $text->update($map[$text->audio_filename]);
                    }
                });
        });
    }

    private function markFailed(string $reason): void
    {
        $this->file->update([
            'status'        => 'failed',
            'error_message' => $reason,
        ]);
    }
}
