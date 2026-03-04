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

class ProcessTsvFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of rows to insert per DB transaction.
     */
    private const CHUNK_SIZE = 500;

    /**
     * Expected number of TSV columns.
     */
    private const EXPECTED_COLS = 7;

    public int $timeout = 600; // 10 minutes max

    public int $tries = 3;

    public function __construct(private readonly File $file) {}

    public function handle(): void
    {
        $path = Storage::disk('local')->path($this->file->path);

        if (!file_exists($path)) {
            $this->markFailed("TSV file not found on disk: {$path}");
            return;
        }

        // Mark as processing
        $this->file->update([
            'status'         => 'processing',
            'error_message'  => null,
            'rows_imported'  => 0,
        ]);

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->markFailed("Cannot open TSV file.");
            return;
        }

        // Count total rows upfront for progress tracking
        $totalRows = $this->countLines($path);
        $this->file->update(['rows_total' => $totalRows]);

        $fileCache   = [];  // "{userId}_{filename}" => file_id (but we use $this->file->id)
        $buffer      = [];
        $rowCount    = 0;
        $skipped     = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                $line = rtrim($line, "\n\r");

                if (empty($line)) {
                    continue;
                }

                $cols = explode("\t", $line);

                if (count($cols) < self::EXPECTED_COLS) {
                    Log::warning("TsvImport: skipping malformed row #{$rowCount}", [
                        'file_id' => $this->file->id,
                        'cols'    => count($cols),
                        'preview' => mb_substr($line, 0, 80),
                    ]);
                    $skipped++;
                    continue;
                }

                [
                    $transcriptId,
                    $audioFilename,
                    $originalTranscript,
                    $normalizedTranscript,
                    $tokenizedTranscript,
                    $duration,
                    $speakerGender,
                ] = array_map('trim', $cols);

                $buffer[] = [
                    'file_id'               => $this->file->id,
                    'transcript_id'         => (int) $transcriptId,
                    'audio_filename'        => $audioFilename,
                    'original_transcript'   => $originalTranscript,
                    'normalized_transcript' => $normalizedTranscript,
                    'tokenized_transcript'  => $tokenizedTranscript,
                    'duration'              => (int) $duration,
                    'speaker_gender'        => strtoupper($speakerGender),
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ];

                $rowCount++;

                if (count($buffer) >= self::CHUNK_SIZE) {
                    $this->flushBuffer($buffer);
                    $buffer = [];

                    // Update progress
                    $this->file->update(['rows_imported' => $rowCount]);
                }
            }

            // Flush remaining rows
            if (!empty($buffer)) {
                $this->flushBuffer($buffer);
            }

            fclose($handle);

            $this->file->update([
                'status'        => 'completed',
                'rows_imported' => $rowCount,
                'rows_total'    => $rowCount + $skipped,
            ]);

            Log::info("TsvImport: completed file #{$this->file->id}", [
                'rows_imported' => $rowCount,
                'rows_skipped'  => $skipped,
            ]);

        } catch (\Throwable $e) {
            fclose($handle);
            $this->markFailed($e->getMessage());
            Log::error("TsvImport: failed for file #{$this->file->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // allow queue to retry
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->markFailed($exception->getMessage());
    }

    // -------------------------------------------------------------------------

    private function flushBuffer(array $rows): void
    {
        DB::transaction(function () use ($rows) {
            DB::table('texts')->insert($rows);
        });
    }

    private function markFailed(string $reason): void
    {
        $this->file->update([
            'status'        => 'failed',
            'error_message' => $reason,
        ]);
    }

    private function countLines(string $path): int
    {
        $count = 0;
        $fp    = fopen($path, 'r');
        while (!feof($fp)) {
            $line = fgets($fp);
            if ($line !== false && trim($line) !== '') {
                $count++;
            }
        }
        fclose($fp);
        return $count;
    }
}
