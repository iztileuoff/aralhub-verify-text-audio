<?php

namespace App\Console\Commands;

use App\Enums\RoleEnum;
use App\Models\Audio;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CalculateSpeakerAudioTimeCommand extends Command
{
    protected $signature = 'speakers:audio-time {--target=400 : Number of audios to estimate the time for}';

    protected $description = 'Calculate the average time each speaker spends to record a target number of audios per day';

    public function handle(): int
    {
        $target = (int) $this->option('target');

        $speakers = User::query()
            ->where('role', RoleEnum::SPEAKER)
            ->orderBy('id')
            ->get();

        if ($speakers->isEmpty()) {
            $this->warn('No speakers found.');

            return self::SUCCESS;
        }

        $rows = $speakers
            ->map(fn (User $speaker): ?array => $this->buildRow($speaker, $target))
            ->filter()
            ->values();

        if ($rows->isEmpty()) {
            $this->warn('No recorded audios found for any speaker.');

            return self::SUCCESS;
        }

        $this->table(
            [
                'Speaker',
                'Audios',
                'Days',
                'Avg/audio',
                'Avg/day',
                "Time for {$target}",
            ],
            $rows->map(fn (array $row): array => [
                $row['speaker'],
                $row['audios'],
                $row['days'],
                $this->formatDuration($row['avg_per_audio']),
                $this->formatDuration($row['avg_per_day']),
                $this->formatDuration($row['time_for_target']),
            ])->all(),
        );

        return self::SUCCESS;
    }

    /**
     * Build a statistics row for a single speaker.
     *
     * @return array{speaker: string, audios: int, days: int, avg_per_audio: float, avg_per_day: float, time_for_target: float}|null
     */
    private function buildRow(User $speaker, int $target): ?array
    {
        $audios = Audio::query()
            ->where('edit_speaker_id', $speaker->id)
            ->whereNotNull('speak_started_at')
            ->whereNotNull('speak_finished_at')
            ->get(['speak_started_at', 'speak_finished_at']);

        if ($audios->isEmpty()) {
            return null;
        }

        $totalSeconds = $audios->sum(
            fn (Audio $audio): int => max(0, (int) $audio->speak_finished_at->diffInSeconds($audio->speak_started_at, true)),
        );

        $audioCount = $audios->count();
        $dayCount = $this->countDistinctDays($audios);

        $averagePerAudio = $totalSeconds / $audioCount;
        $averagePerDay = $totalSeconds / $dayCount;

        return [
            'speaker' => trim("{$speaker->first_name} {$speaker->last_name}")." (#{$speaker->id})",
            'audios' => $audioCount,
            'days' => $dayCount,
            'avg_per_audio' => $averagePerAudio,
            'avg_per_day' => $averagePerDay,
            'time_for_target' => $averagePerAudio * $target,
        ];
    }

    /**
     * Count the distinct calendar days the speaker recorded audios on.
     *
     * @param  Collection<int, Audio>  $audios
     */
    private function countDistinctDays(Collection $audios): int
    {
        return $audios
            ->map(fn (Audio $audio): string => $audio->speak_finished_at->toDateString())
            ->unique()
            ->count();
    }

    private function formatDuration(float $seconds): string
    {
        $seconds = (int) round($seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remaining = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remaining);
    }
}
