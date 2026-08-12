<?php

namespace App\Console\Commands;

use App\Models\Text;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class CheckDuplicateTextsCommand extends Command
{
    protected $signature = 'import:check-duplicates
        {filename : Candidate file on the public disk, one transcript per line}
        {--out= : Write the deduplicated lines to this file on the public disk}
        {--file-id= : Compare against one dataset only; by default against every existing text}
        {--examples=5 : How many duplicate lines to print as a sample}';

    protected $description = 'Report lines of a candidate import file that repeat inside it or already exist in the database';

    public function handle(): int
    {
        $filename = $this->argument('filename');
        $out = $this->option('out');
        $examples = max(0, (int) $this->option('examples'));
        $disk = Storage::disk('public');

        if (! $disk->exists($filename)) {
            $this->error("File {$filename} not found in public disk.");

            return self::INVALID;
        }

        if ($out !== null && $out === $filename) {
            $this->error('The --out file must differ from the source file.');

            return self::INVALID;
        }

        $lines = $this->readLines($disk->get($filename));

        $fileIdOption = $this->option('file-id');
        $fileId = $fileIdOption === null || $fileIdOption === '' ? null : (int) $fileIdOption;

        $this->line('Reading existing transcripts…');
        $existing = $this->existingTranscripts($fileId);
        $this->line('Indexed '.count($existing).' existing transcripts.');

        $seen = [];
        $kept = [];
        $repeated = [];
        $known = [];

        foreach ($lines as $line) {
            $key = $this->normalize($line);

            if (isset($seen[$key])) {
                $repeated[] = $line;

                continue;
            }

            $seen[$key] = true;

            if (isset($existing[$key])) {
                $known[] = $line;

                continue;
            }

            $kept[] = $line;
        }

        $this->report($lines, $repeated, $known, $kept, $examples);

        if ($out !== null) {
            // LF regardless of the host OS: the file feeds import:text-file.
            $disk->put($out, implode("\n", $kept)."\n");
            $this->info('Wrote '.count($kept)." unique lines to {$out}.");
        }

        return self::SUCCESS;
    }

    /**
     * Split the raw file into trimmed, non-empty lines — the same way
     * import:text-file does, so the counts match what would be imported.
     *
     * @return list<string>
     */
    private function readLines(string $contents): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $contents)),
            fn (string $line): bool => $line !== '',
        ));
    }

    /**
     * Set of transcripts already in the database, keyed by their normalized
     * form. Rows imported before normalization existed keep NULL in
     * edit_normalized_transcript, so those fall back to the original.
     *
     * @return array<string, true>
     */
    private function existingTranscripts(?int $fileId): array
    {
        $existing = [];

        Text::query()
            ->when($fileId !== null, fn (Builder $query) => $query->where('file_id', $fileId))
            ->select(['edit_original_transcript', 'edit_normalized_transcript'])
            ->lazy()
            ->each(function (Text $text) use (&$existing): void {
                $key = $text->edit_normalized_transcript
                    ?? ($text->edit_original_transcript === null
                        ? null
                        : $this->normalize($text->edit_original_transcript));

                if ($key !== null && $key !== '') {
                    $existing[$key] = true;
                }
            });

        return $existing;
    }

    /**
     * The identity used for comparison: the normalization import:text-file
     * stores in edit_normalized_transcript, so "Salem." and "salem" are the
     * same sentence and would only be recorded twice for nothing.
     */
    private function normalize(string $transcript): string
    {
        return mb_strtolower(trim($transcript, '.?!'));
    }

    /**
     * @param  list<string>  $lines
     * @param  list<string>  $repeated
     * @param  list<string>  $known
     * @param  list<string>  $kept
     */
    private function report(array $lines, array $repeated, array $known, array $kept, int $examples): void
    {
        $this->newLine();
        $this->table(['', 'Lines'], [
            ['Read from file', count($lines)],
            ['Repeated inside the file', count($repeated)],
            ['Already in the database', count($known)],
            ['Unique and new', count($kept)],
        ]);

        $this->printExamples('Repeated inside the file', $repeated, $examples);
        $this->printExamples('Already in the database', $known, $examples);

        if ($repeated === [] && $known === []) {
            $this->info('No duplicates found.');
        }
    }

    /**
     * @param  list<string>  $duplicates
     */
    private function printExamples(string $title, array $duplicates, int $examples): void
    {
        if ($duplicates === [] || $examples === 0) {
            return;
        }

        $this->newLine();
        $this->line("<comment>{$title}</comment> — first ".min($examples, count($duplicates)).':');

        foreach (array_slice($duplicates, 0, $examples) as $line) {
            $this->line('  '.$line);
        }
    }
}
