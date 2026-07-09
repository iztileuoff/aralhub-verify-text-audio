<?php

namespace App\Jobs;

use App\Enums\GenderEnum;
use App\Models\Text;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class UploadSpeakAudioToYandex implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 120;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function __construct(
        public int $textId,
        public string $filename,
        public ?string $previousFilename,
        public int $speakerId,
        public ?string $speakerGender,
        public ?string $speakStartedAt,
        public string $speakFinishedAt,
    ) {}

    /**
     * Upload the locally buffered recording to Yandex S3 and, only once the
     * upload succeeds, persist the Audio record so a storage failure never
     * leaves the dataset with audio that does not exist in the bucket.
     */
    public function handle(): void
    {
        $localDisk = Storage::disk('local');

        if (! $localDisk->exists($this->filename)) {
            Log::warning('Local speak audio missing, skipping upload', [
                'text_id' => $this->textId,
                'filename' => $this->filename,
            ]);

            return;
        }

        $stream = $localDisk->readStream($this->filename);
        $uploaded = Storage::disk('yandex-s3')->writeStream($this->filename, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        if ($uploaded === false) {
            throw new RuntimeException('Failed to upload speak audio to Yandex S3: '.$this->filename);
        }

        $text = Text::find($this->textId);

        if (! $text) {
            $localDisk->delete($this->filename);

            return;
        }

        $text->audio()->firstOrCreate(
            ['edit_audio_filename' => $this->filename],
            [
                'speak_started_at' => $this->speakStartedAt,
                'speak_finished_at' => $this->speakFinishedAt,
                'edit_speaker_id' => $this->speakerId,
                'edit_speaker_gender' => $this->speakerGender,
            ],
        );

        $this->refreshAudioCounters($text);

        if ($this->previousFilename) {
            Storage::disk('yandex-s3')->delete($this->previousFilename);
        }

        $localDisk->delete($this->filename);
    }

    /**
     * Recompute the cached audio counters on the text in a single query
     * instead of three separate COUNT round-trips.
     */
    private function refreshAudioCounters(Text $text): void
    {
        $counts = $text->audio()
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN edit_speaker_gender = ? THEN 1 ELSE 0 END) as male_count', [GenderEnum::MALE->value])
            ->selectRaw('SUM(CASE WHEN edit_speaker_gender = ? THEN 1 ELSE 0 END) as female_count', [GenderEnum::FEMALE->value])
            ->first();

        $text->audio_count = (int) $counts->total_count;
        $text->audio_male_count = (int) $counts->male_count;
        $text->audio_female_count = (int) $counts->female_count;
        $text->save();
    }

    /**
     * The upload exhausted every retry: the recording could not be stored, so
     * per the "no audio without storage" rule no Audio record was created.
     * Drop the local buffer and raise a loud log entry for follow-up.
     */
    public function failed(?Throwable $exception): void
    {
        Storage::disk('local')->delete($this->filename);

        Log::error('Speak audio upload to Yandex S3 permanently failed; recording discarded', [
            'text_id' => $this->textId,
            'filename' => $this->filename,
            'speaker_id' => $this->speakerId,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
