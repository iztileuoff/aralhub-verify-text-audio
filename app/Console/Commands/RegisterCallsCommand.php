<?php

namespace App\Console\Commands;

use App\Enums\CallStatusEnum;
use App\Models\CallRecording;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileAttributes;

class RegisterCallsCommand extends Command
{
    /** Formats a browser can decode — the trimming editor runs there. */
    private const AUDIO_EXTENSIONS = ['mp3', 'wav', 'ogg', 'oga', 'opus', 'm4a', 'aac', 'flac', 'webm'];

    private const CHUNK = 500;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calls:register
        {--prefix=source/ : Folder in the calls bucket to scan, recursively}
        {--batch= : Batch label for the new calls; defaults to the folder name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register the phone calls uploaded to the calls bucket as pending calls (idempotent)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $prefix = trim((string) $this->option('prefix'), '/');
        $batch = $this->option('batch') ?: (basename($prefix) ?: null);

        $found = 0;
        $added = 0;
        $unsupported = [];
        $pending = [];

        // listContents carries the file size, so the bucket is listed without a request per object.
        foreach (Storage::disk(CallRecording::disk())->getDriver()->listContents($prefix, true) as $item) {
            if (! $item instanceof FileAttributes) {
                continue;
            }

            $extension = strtolower(pathinfo($item->path(), PATHINFO_EXTENSION));

            if (! in_array($extension, self::AUDIO_EXTENSIONS, true)) {
                $unsupported[$extension ?: '(none)'] = ($unsupported[$extension ?: '(none)'] ?? 0) + 1;

                continue;
            }

            $found++;
            $pending[$item->path()] = $item->fileSize();

            if (count($pending) >= self::CHUNK) {
                $added += $this->insertNew($pending, $batch);
                $pending = [];
            }
        }

        $added += $this->insertNew($pending, $batch);

        $this->info("Found {$found} audio files under \"{$prefix}\", registered {$added} new calls (batch: ".($batch ?? 'none').').');

        if ($unsupported !== []) {
            $summary = collect($unsupported)->map(fn (int $count, string $extension): string => "{$extension}: {$count}")->implode(', ');
            $this->warn("Skipped files the browser cannot open — convert them first: {$summary}.");
        }

        return self::SUCCESS;
    }

    /**
     * Insert the calls not registered yet; the unique source_path makes a
     * re-run after an interrupted or extended upload safe.
     *
     * @param  array<string, int|null>  $files  path => size in bytes
     */
    private function insertNew(array $files, ?string $batch): int
    {
        if ($files === []) {
            return 0;
        }

        $known = CallRecording::query()
            ->whereIn('source_path', array_keys($files))
            ->pluck('source_path')
            ->flip();

        $now = now();
        $rows = [];

        foreach ($files as $path => $size) {
            if (! isset($known[$path])) {
                $rows[] = [
                    'batch' => $batch,
                    'original_filename' => basename($path),
                    'source_path' => $path,
                    'size_bytes' => $size,
                    'status' => CallStatusEnum::PENDING->value,
                    'rejections_count' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            CallRecording::query()->insert($rows);
        }

        return count($rows);
    }
}
