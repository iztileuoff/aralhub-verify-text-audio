<?php

namespace Database\Factories;

use App\Enums\CallSkipReasonEnum;
use App\Enums\CallStatusEnum;
use App\Models\CallRecording;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallRecording>
 */
class CallRecordingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->numerify('call_##########').'.mp3';

        return [
            'batch' => 'calls-test',
            'original_filename' => $name,
            'source_path' => 'source/calls-test/'.$name,
            'size_bytes' => fake()->numberBetween(100_000, 2_000_000),
            'status' => CallStatusEnum::PENDING,
            'rejections_count' => 0,
        ];
    }

    /**
     * Taken by a trimmer who is working on it now.
     */
    public function inProgress(?User $trimmer = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CallStatusEnum::IN_PROGRESS,
            'trimmer_id' => $trimmer?->id ?? User::factory()->trimmer(),
            'assigned_at' => now(),
        ]);
    }

    /**
     * Trimmed, transcribed and waiting for moderation.
     */
    public function submitted(?User $trimmer = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CallStatusEnum::SUBMITTED,
            'trimmer_id' => $trimmer?->id ?? User::factory()->trimmer(),
            'assigned_at' => now()->subMinutes(5),
            'source_duration_ms' => 95_000,
            'trim_start_ms' => 4_000,
            'trim_end_ms' => 90_000,
            'trimmed_path' => 'trimmed/'.fake()->unique()->lexify('????????????').'.wav',
            'trimmed_duration' => 86 * 16000,
            'transcript' => 'Assalawma áleykum, qalay jaǵdayıńız?',
            'normalized_transcript' => 'assalawma áleykum, qalay jaǵdayıńız',
            'tokenized_transcript' => 'a s s a l a w m a | á l e y k u m , | q a l a y | j a ǵ d a y ı ń ı z |',
            'submitted_at' => now(),
        ]);
    }

    /**
     * Accepted by a moderator.
     */
    public function approved(?User $trimmer = null): static
    {
        return $this->submitted($trimmer)->state(fn (array $attributes): array => [
            'status' => CallStatusEnum::APPROVED,
            'moderator_id' => User::factory()->admin(),
            'moderated_at' => now(),
        ]);
    }

    /**
     * Sent back to its trimmer with a reason.
     */
    public function rejected(?User $trimmer = null): static
    {
        return $this->submitted($trimmer)->state(fn (array $attributes): array => [
            'status' => CallStatusEnum::REJECTED,
            'rejections_count' => 1,
            'reject_reason' => 'Bası kesilmegen.',
            'moderator_id' => User::factory()->admin(),
            'moderated_at' => now(),
        ]);
    }

    /**
     * Marked unusable by its trimmer.
     */
    public function skipped(?User $trimmer = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CallStatusEnum::SKIPPED,
            'trimmer_id' => $trimmer?->id ?? User::factory()->trimmer(),
            'skip_reason' => CallSkipReasonEnum::NO_SPEECH,
        ]);
    }
}
