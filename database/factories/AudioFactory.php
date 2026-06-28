<?php

namespace Database\Factories;

use App\Enums\AudioSplitStatusEnum;
use App\Enums\GenderEnum;
use App\Models\Audio;
use App\Models\Text;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Audio>
 */
class AudioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'text_id' => Text::factory(),
            'edit_audio_filename' => 'audio/'.fake()->unique()->lexify('??????????').'.mp3',
            'edit_speaker_gender' => fake()->randomElement(GenderEnum::cases()),
            'is_correct' => true,
            'edit_converted_audio_duration' => null,
            'split_status' => AudioSplitStatusEnum::NONE,
        ];
    }

    /**
     * Audio that exceeds the STT length limit and awaits splitting.
     */
    public function pendingSplit(): static
    {
        return $this->state(fn (): array => [
            'edit_converted_audio_duration' => Audio::STT_MAX_DURATION_SAMPLES,
            'split_status' => AudioSplitStatusEnum::PENDING,
        ]);
    }
}
