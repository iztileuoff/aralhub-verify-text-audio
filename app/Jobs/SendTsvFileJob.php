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

    // Снижаем ожидаемое количество до 5 (нам важны только поля для БД)
    private const EXPECTED_COLS = 5;

    private const CHUNK_SIZE = 500;

    // Увеличиваем таймаут джоба, чтобы он дождался ответа HTTP-клиента
    public int $timeout = 600;

    public int $tries = 3;

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function __construct(private readonly File $file) {}

    /**
     * @throws ConnectionException
     */
    public function handle(): void
    {
        $path = Storage::disk('public')->path($this->file->path);

        if (! file_exists($path)) {
            $this->markFailed("TSV file not found on disk: {$path}");
            return;
        }

        $this->file->update(['status' => 'sending']);

        Log::info("SendTsvFile: sending file #{$this->file->id} to translation API");

        // Отправка файла
        $response = Http::timeout(600)
            ->withHeaders([
                'Expect' => '',
                'Connection' => 'close', // Close connection immediately
                'Accept-Encoding' => 'gzip, deflate',
            ])
            ->withOptions([
                'version' => 1.0, // Force HTTP/1.0
                'decode_content' => false,
                'curl' => [
                    CURLOPT_ENCODING => '',
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0,
                    CURLOPT_FORBID_REUSE => true,
                    CURLOPT_FRESH_CONNECT => true,
                ],
            ])
            ->attach(
                'file',
                fopen($path, 'r'),
                $this->file->filename
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

        // Парсинг ответа
        $updated = $this->parseAndUpdate($response->body());

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

    private function parseAndUpdate(string $body): int
    {
        $lines = explode("\n", $body);
        $buffer = [];
        $count = 0;
        $skipped = 0;
        $isFirstLine = true;

        foreach ($lines as $line) {
            $line = rtrim($line, "\r");

            if (empty($line)) {
                continue;
            }

            $cols = explode("\t", $line);

            // Пропускаем строку с заголовками
            if ($isFirstLine && str_contains($cols[0], 'transcript_id')) {
                $isFirstLine = false;
                continue;
            }
            $isFirstLine = false;

            // Проверяем только необходимые 5 колонок
            if (count($cols) < self::EXPECTED_COLS) {
                Log::warning('SendTsvFile: skipping malformed response row', [
                    'file_id' => $this->file->id,
                    'cols' => count($cols),
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

    private function flushUpdates(array $rows): void
    {
        $filenames = array_column($rows, 'audio_filename');
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
