<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Models\Text;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Common\Exception\UnsupportedTypeException;
use OpenSpout\Reader\Exception\ReaderNotOpenedException;
use Rap2hpoutre\FastExcel\FastExcel;

class ImportExcelFileCommand extends Command
{
    protected $signature = 'import:excel-file
        {filename : Name of the file on the public disk}
        {--user=1 : Owner of the created File record}
        {--file-id= : Append to an existing dataset file instead of creating a new one}
        {--label= : Human-readable dataset name, e.g. "v3"; on append it renames the dataset}
        {--chunk=1000 : Rows per bulk insert}';

    protected $description = 'Import an Excel/CSV file and create records';

    /**
     * @throws IOException
     * @throws UnsupportedTypeException
     * @throws ReaderNotOpenedException
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

        $collection = (new FastExcel)->import($disk->path($filename));

        $fileIdOption = $this->option('file-id');
        $appendTo = $fileIdOption === null || $fileIdOption === '' ? null : (int) $fileIdOption;

        if ($appendTo !== null && ! File::query()->whereKey($appendTo)->exists()) {
            $this->error("File record {$appendTo} not found; drop --file-id to create a new one.");

            return self::INVALID;
        }

        // Keep the raw batch on disk either way; on append the File record goes
        // on pointing at the first batch, so its `path` stays the original.
        $storedPath = "tsv_uploads/{$userId}/{$filename}";
        $disk->copy($filename, $storedPath);

        $label = $this->option('label');
        $label = $label === null || $label === '' ? null : trim($label);

        $file = $appendTo !== null
            ? File::query()->findOrFail($appendTo)
            : File::create([
                'filename' => $filename,
                'label' => $label,
                'path' => $storedPath,
                'mime_type' => $disk->mimeType($filename),
                'size' => $disk->size($filename),
                'user_id' => $userId,
                'status' => File::STATUS_PROCESSING,
                'rows_total' => $collection->count(),
            ]);

        $now = now();
        $blank = 0;
        $repeated = 0;
        $known = 0;
        $rows = [];

        $seen = $appendTo === null ? [] : $this->existingTranscripts($file->id);

        foreach ($collection as $item) {
            $transcript = trim((string) $this->column($item, 'text'));

            if ($transcript === '') {
                $blank++;

                continue;
            }

            $normalized = $this->normalize($transcript);

            if (isset($seen[$normalized])) {
                if ($seen[$normalized] === 'db') {
                    $known++;
                } else {
                    $repeated++;
                }

                continue;
            }

            $seen[$normalized] = 'batch';

            $rows[] = [
                'file_id' => $file->id,
                'transcript_id' => $this->transcriptId($this->column($item, 'id')),
                'edit_original_transcript' => $transcript,
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

        DB::beginTransaction();

        try {
            $imported = 0;

            foreach (array_chunk($rows, $chunkSize) as $chunk) {
                Text::query()->insert($chunk);
                $imported += count($chunk);
            }

            $attributes = [
                'status' => File::STATUS_COMPLETED,
                'rows_total' => $appendTo === null
                    ? $collection->count()
                    : (int) $file->rows_total + $collection->count(),
                'rows_imported' => (int) $file->rows_imported + $imported,
            ];

            // On append the label is optional: given, it renames the dataset;
            // omitted, the existing name stays.
            if ($label !== null) {
                $attributes['label'] = $label;
            }

            $file->update($attributes);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            $file->update([
                'status' => File::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->report($file, $collection->count(), $imported, $blank, $repeated, $known, $appendTo);

        return self::SUCCESS;
    }

    /**
     * Read a column by name regardless of how the header was capitalised —
     * spreadsheets arrive with "id", "ID" and "Id" from different producers.
     *
     * @param  array<string, mixed>  $row
     */
    private function column(array $row, string $name): mixed
    {
        foreach ($row as $key => $value) {
            if (mb_strtolower(trim((string) $key)) === $name) {
                return $value;
            }
        }

        return null;
    }

    /**
     * The source id is metadata, and `transcript_id` is an unsigned integer —
     * so "kaa-000042" is kept as 42 and anything without digits becomes NULL
     * rather than failing the whole import.
     */
    private function transcriptId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? null : (int) $digits;
    }

    /**
     * Normalised transcripts already stored under this dataset, used to keep a
     * re-sent batch from landing twice when appending.
     *
     * @return array<string, string>
     */
    private function existingTranscripts(int $fileId): array
    {
        $existing = [];

        Text::query()
            ->where('file_id', $fileId)
            ->whereNotNull('edit_original_transcript')
            ->select('edit_original_transcript')
            ->lazy()
            ->each(function (Text $text) use (&$existing): void {
                $existing[$this->normalize($text->edit_original_transcript)] = 'db';
            });

        return $existing;
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

    private function report(
        File $file,
        int $total,
        int $imported,
        int $blank,
        int $repeated,
        int $known,
        ?int $appendTo,
    ): void {
        $name = $file->label ? "{$file->label} — {$file->filename}" : $file->filename;

        $this->newLine();
        $this->line($appendTo === null
            ? "Created file #{$file->id} ({$name})."
            : "Appended to file #{$file->id} ({$name}).");

        $this->table(['', 'Rows'], [
            ['In the spreadsheet', $total],
            ['Imported', $imported],
            ['Skipped — empty text', $blank],
            ['Skipped — repeated in this file', $repeated],
            ['Skipped — already in the dataset', $known],
        ]);
    }
}
