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

    // Изменили ожидаемое количество колонок на 5, так как для обновления нужны только первые 5
    private const EXPECTED_COLS = 5;

    private const CHUNK_SIZE = 500;

    public int $timeout = 300;

    public int $tries = 3;

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function __construct(private readonly File $file) {}

    public function handle(): void
    {
        $path = Storage::disk('public')->path($this->file->path);

        if (! file_exists($path)) {
            $this->markFailed("TSV file not found on disk: {$path}");

            return;
        }

        $this->file->update(['status' => 'sending']);

        Log::info("SendTsvFile: sending file #{$this->file->id} to translation API");

        // ── 1. Отправляем файл и получаем ресурс потока (stream) ──────────────
        $stream = $this->sendWithCurl($path);

        if ($stream === null) {
            // markFailed уже вызван внутри sendWithCurl
            return;
        }

        // ── 2. Парсим ответ построчно и обновляем БД ──────────────────────────
        $updated = $this->parseAndUpdate($stream);

        // Обязательно закрываем временный файл, чтобы освободить ресурсы
        fclose($stream);

        // ── 3. Отмечаем успешное завершение ───────────────────────────────────
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

    // ── Private: HTTP ─────────────────────────────────────────────────────────

    /**
     * Send the TSV file using raw cURL.
     * Returns a file pointer resource instead of a string to save RAM.
     */
    private function sendWithCurl(string $filePath)
    {
        $tmpFile = tmpfile();

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => self::TRANSLATE_ENDPOINT,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'file' => new \CURLFile(
                    $filePath,
                    $this->file->mime_type,
                    $this->file->filename
                ),
            ],
            // ── Fixes for error 18 ────────────────────────────────────────
            CURLOPT_IGNORE_CONTENT_LENGTH => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            // ── Пишем ответ во временный файл (предотвращает OOM) ─────────
            CURLOPT_FILE => $tmpFile,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 240,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $success = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        $curlErrNo = curl_errno($curl);
        curl_close($curl);

        // cURL error 18 означает частичные данные — всё равно пытаемся их использовать
        if (! $success && $curlErrNo !== CURLE_PARTIAL_FILE) {
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

        Log::info("SendTsvFile: received response for file #{$this->file->id}", [
            'http_code' => $httpCode,
            'curl_errno' => $curlErrNo,
        ]);

        // Возвращаем указатель в начало файла и отдаем ресурс
        fseek($tmpFile, 0);

        return $tmpFile;
    }

    // ── Private: Parser ───────────────────────────────────────────────────────

    /**
     * Parse TSV stream and update filter_* columns on matching Text rows.
     */
    private function parseAndUpdate($stream): int
    {
        $buffer = [];
        $count = 0;
        $skipped = 0;
        $isFirstLine = true;

        // Читаем поток построчно (максимальная экономия RAM)
        while (($line = fgets($stream)) !== false) {
            $line = rtrim($line, "\r\n");

            if (empty($line)) {
                continue;
            }

            $cols = explode("\t", $line);

            // Пропускаем строку заголовков, если она пришла
            if ($isFirstLine && str_contains($cols[0], 'transcript_id')) {
                $isFirstLine = false;
                continue;
            }
            $isFirstLine = false;

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

        if (! empty($buffer)) {
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
            'status' => 'failed',
            'error_message' => $reason,
        ]);
    }
}
