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
    protected $signature = 'import:excel-file {filename} {--user=1}';

    protected $description = 'Import an Excel/CSV file and create records';

    /**
     * @throws IOException
     * @throws UnsupportedTypeException
     * @throws ReaderNotOpenedException
     * @throws \Throwable
     */
    public function handle(): void
    {
        $filename = $this->argument('filename');
        $userId = $this->option('user');
        $disk = Storage::disk('public');

        if (!$disk->exists($filename)) {
            $this->error("File {$filename} not found in public disk.");
            return;
        }

        $collection = (new FastExcel())->import($disk->path($filename));

        // Store the raw file in a specific directory
        $storedPath = "tsv_uploads/{$userId}/{$filename}";
        $disk->copy($filename, $storedPath);

        // Create a File record immediately so the user can track it
        $file = File::create([
            'filename' => $filename,
            'path' => $storedPath,
            'mime_type' => $disk->mimeType($filename),
            'size' => $disk->size($filename),
            'user_id' => $userId,
            'status' => File::STATUS_PROCESSING,
            'rows_total' => $collection->count(),
        ]);

        $now = now();

        DB::beginTransaction();

        try {
            $imported = 0;
            foreach ($collection as $item) {
                $transcriptId = $item['ID'];
                $editOriginalTranscript = $item['text'];

                // 2. Normalized Transcript
                // Приводим к нижнему регистру и удаляем финальную точку
                $editNormalizedTranscript = mb_strtolower(trim($editOriginalTranscript, '.?!'));

                // 3. Tokenized Transcript
                // Разбиваем на слова, затем каждое слово на буквы через пробел, разделяя слова пайпом |
                $words = explode(' ', $editNormalizedTranscript);
                $tokenizedParts = array_map(function ($word) {
                    // mb_str_split корректно разбивает казахские символы (қ, ө, ұ и т.д.)
                    $chars = mb_str_split($word);

                    return implode(' ', $chars).' |';
                }, $words);

                $editTokenizedTranscript = implode(' ', $tokenizedParts);

                Text::create([
                    'file_id' => $file->id,
                    'transcript_id' => $transcriptId,
                    'edit_original_transcript' => $editOriginalTranscript,
                    'edit_normalized_transcript' => $editNormalizedTranscript,
                    'edit_tokenized_transcript' => $editTokenizedTranscript,
                    'edit_user_id' => $userId,
                    'edit_started_at' => $now,
                    'edit_finished_at' => $now,
                    'is_main' => true,
                ]);

                $imported++;
            }

            $file->update([
                'status' => File::STATUS_COMPLETED,
                'rows_imported' => $imported,
            ]);

            DB::commit();
            $this->info('Import completed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            $file->update([
                'status' => File::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            $this->error($e->getMessage());
        }
    }
}
