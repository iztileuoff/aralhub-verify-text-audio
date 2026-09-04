<?php

namespace App\Console\Commands;

use App\Enums\AudioSplitStatusEnum;
use App\Models\Audio;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dumps every verified audio of every dataset together with the speaker who
 * read it and the place its file lives. This is the one command that is
 * deliberately not scoped to the active dataset: a voice belongs to a speaker,
 * not to a dataset, and the speaker → audio link exists only in the database
 * (the export .tsv carries no speaker column). See docs/adr/0001.
 *
 * It is a view, not an export step: nothing is stamped and no Export is created.
 */
class ExportSpeakerManifestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audio:export-speaker-manifest
        {--filename=speaker_manifest.tsv : Output file under storage/app/}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dump every verified audio across all datasets with its speaker, storage location and transcripts (read-only)';

    /**
     * Header of the manifest; the row builder emits values in this order.
     *
     * @var list<string>
     */
    public const COLUMNS = [
        'audio_id',
        'speaker_id',
        'gender',
        'age',
        'file_id',
        'text_id',
        'is_split_part',
        'parent_audio_id',
        'audio_filename',
        'storage_disk',
        'audio_url',
        'converted_filename',
        'duration_samples',
        'duration_s',
        'recorded_at',
        'moderated_at',
        'exported_at',
        'export_id',
        'transcript_original',
        'transcript_normalized',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = storage_path('app/'.$this->option('filename'));

        $file = @fopen($path, 'w');

        if ($file === false) {
            $this->error("Cannot write to {$path}.");

            return self::FAILURE;
        }

        fwrite($file, implode("\t", self::COLUMNS).PHP_EOL);

        $audios = Audio::query()
            ->with([
                'text:id,file_id,is_split_part,edit_original_transcript,edit_normalized_transcript',
                'editSpeaker:id,gender,age',
            ])
            ->where('is_correct', true)
            ->where(function (Builder $query): void {
                $query->whereNull('split_status')
                    ->orWhere('split_status', '!=', AudioSplitStatusEnum::SPLIT->value);
            })
            ->orderBy('id')
            ->lazy();

        $count = 0;
        $speakers = [];

        foreach ($audios as $audio) {
            fwrite($file, implode("\t", $this->row($audio)).PHP_EOL);

            $speakers[$audio->edit_speaker_id ?? 0] = true;
            $count++;
        }

        fclose($file);

        $this->info("Exported {$count} verified audios of ".count($speakers)." speakers to {$path}.");

        return self::SUCCESS;
    }

    /**
     * One manifest row, in COLUMNS order.
     *
     * @return list<string|int|null>
     */
    private function row(Audio $audio): array
    {
        $disk = $audio->audioDisk();
        $samples = $audio->edit_converted_audio_duration;

        return [
            $audio->id,
            $audio->edit_speaker_id,
            $audio->edit_speaker_gender?->value ?? $audio->editSpeaker?->gender?->value,
            $audio->editSpeaker?->age,
            $audio->text?->file_id,
            $audio->text_id,
            $audio->text?->is_split_part ? 1 : 0,
            $audio->parent_audio_id,
            $audio->edit_audio_filename,
            $disk,
            $this->publicUrl($disk, $audio->edit_audio_filename),
            $audio->edit_converted_audio_filename,
            $samples,
            $samples === null ? null : round($samples / Audio::SAMPLE_RATE, 2),
            $audio->speak_finished_at?->toDateTimeString(),
            $audio->moderator_finished_at?->toDateTimeString(),
            $audio->exported_at?->toDateTimeString(),
            $audio->export_id,
            $this->flatten($audio->text?->edit_original_transcript),
            $this->flatten($audio->text?->edit_normalized_transcript),
        ];
    }

    /**
     * Public URL of the original, built from the disk's configured base URL so
     * that no storage client is instantiated for a hundred thousand rows.
     */
    private function publicUrl(string $disk, ?string $filename): ?string
    {
        $base = config("filesystems.disks.{$disk}.url");

        if (! $base || ! $filename) {
            return null;
        }

        return rtrim($base, '/').'/'.ltrim($filename, '/');
    }

    /**
     * Keep a transcript on one line: tabs and line breaks would break the .tsv.
     */
    private function flatten(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        return trim(preg_replace('/[\t\r\n]+/', ' ', $text));
    }
}
