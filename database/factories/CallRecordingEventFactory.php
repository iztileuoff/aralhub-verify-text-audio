<?php

namespace Database\Factories;

use App\Enums\CallEventTypeEnum;
use App\Models\CallRecording;
use App\Models\CallRecordingEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallRecordingEvent>
 */
class CallRecordingEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $trimmer = User::factory()->trimmer();

        return [
            'call_recording_id' => CallRecording::factory(),
            'user_id' => $trimmer,
            'trimmer_id' => $trimmer,
            'type' => CallEventTypeEnum::SUBMITTED,
            'trimmed_duration' => 60 * 16000,
        ];
    }
}
