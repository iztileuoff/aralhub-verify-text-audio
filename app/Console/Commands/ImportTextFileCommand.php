<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Models\Text;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ImportTextFileCommand extends Command
{
    protected $signature = 'import:text-file
        {filename : Name of the file on the public disk}
        {--user=1 : Owner of the created File record}
        {--chunk=1000 : Rows per bulk insert}';

    protected $description = 'Import a plain text file (one transcript per line) and create records';

    /**
     * @throws \Throwable
     */
    public function handle(): int
    {
        $filename = $this->argument('filename');
        $userId = (int) $this->option('user');
        $chunkSize = (int) $this->option('chunk');
        $disk = Storage::disk('public');

        if ($chunkSize < 1) {
            $this->error('The --chunk option must be a positive number.');

            return self::INVALID;
        }

        if (! $disk->exists($filename)) {
            $this->error("File {$filename} not found in public disk.");

            return self::INVALID;
        }

        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $disk->get($filename))),
            fn (string $line): bool => $line !== '',
        ));

        // Store the raw file in a specific directory
        $storedPath = "txt_uploads/{$userId}/{$filename}";
        $disk->copy($filename, $storedPath);

        // Create a File record immediately so the user can track it
        $file = File::create([
            'filename' => $filename,
            'path' => $storedPath,
            'mime_type' => $disk->mimeType($filename),
            'size' => $disk->size($filename),
            'user_id' => $userId,
            'status' => File::STATUS_PROCESSING,
            'rows_total' => count($lines),
        ]);

        $now = now();
        $imported = 0;

        DB::beginTransaction();

        try {
            $bar = $this->output->createProgressBar(count($lines));

            foreach (array_chunk($lines, $chunkSize) as $chunk) {
                $rows = array_map(
                    fn (string $line): array => $this->buildRow($line, $file->id, $userId, $now),
                    $chunk,
                );

                Text::query()->insert($rows);

                $imported += count($rows);
                $bar->advance(count($rows));
            }

            $bar->finish();
            $this->newLine();

            $file->update([
                'status' => File::STATUS_COMPLETED,
                'rows_imported' => $imported,
            ]);

            DB::commit();
            $this->info("Import completed successfully: {$imported} rows.");

            return self::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();

            $file->update([
                'status' => File::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Build one insert row. Bulk inserts bypass the model, so the timestamps
     * Text::create() used to fill are set here explicitly; the remaining
     * columns keep their database defaults, as they did before.
     *
     * @return array<string, mixed>
     */
    private function buildRow(string $line, int $fileId, int $userId, CarbonInterface $now): array
    {
        $normalized = $this->normalize($line);

        return [
            'file_id' => $fileId,
            'transcript_id' => null,
            'edit_original_transcript' => $line,
            'edit_normalized_transcript' => $normalized,
            'edit_tokenized_transcript' => $this->tokenize($normalized),
            'edit_user_id' => $userId,
            'edit_started_at' => $now,
            'edit_finished_at' => $now,
            'is_main' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Lowercase the transcript and drop the sentence punctuation around it.
     */
    private function normalize(string $transcript): string
    {
        return mb_strtolower(trim($transcript, '.?!'));
    }

    /**
     * Split into words, then each word into space-separated characters, with
     * a pipe closing every word. mb_str_split keeps the Karakalpak letters
     * (қ, ө, ұ and the like) intact instead of cutting them mid-byte.
     */
    private function tokenize(string $normalized): string
    {
        $words = array_map(
            fn (string $word): string => implode(' ', mb_str_split($word)).' |',
            explode(' ', $normalized),
        );

        return implode(' ', $words);
    }
}
