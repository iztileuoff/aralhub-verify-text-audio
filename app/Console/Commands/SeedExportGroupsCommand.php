<?php

namespace App\Console\Commands;

use App\Models\Audio;
use App\Models\Export;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class SeedExportGroupsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:seed-groups {--legacy-file-id=8}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill historical export groups: file_id < N to a legacy export, file_id = N to its own export';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fileId = (int) $this->option('legacy-file-id');

        $legacy = Export::firstOrCreate(['name' => "Legacy (file_id < {$fileId})"]);
        $current = Export::firstOrCreate(['name' => "file_id = {$fileId}"]);

        $legacyCount = Audio::query()
            ->whereNull('export_id')
            ->whereNotNull('exported_at')
            ->whereHas('text', fn (Builder $query) => $query->where('file_id', '<', $fileId))
            ->update(['export_id' => $legacy->id]);

        $currentCount = Audio::query()
            ->whereNull('export_id')
            ->whereNotNull('exported_at')
            ->whereHas('text', fn (Builder $query) => $query->where('file_id', $fileId))
            ->update(['export_id' => $current->id]);

        $legacy->update(['exported_count' => $legacy->audios()->count()]);
        $current->update(['exported_count' => $current->audios()->count()]);

        $this->info("Assigned {$legacyCount} audios to export #{$legacy->id} (legacy) and {$currentCount} to export #{$current->id} (file_id = {$fileId}).");

        return self::SUCCESS;
    }
}
