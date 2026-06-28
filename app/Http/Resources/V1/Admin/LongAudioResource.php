<?php

namespace App\Http\Resources\V1\Admin;

use App\Models\Audio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Audio */
class LongAudioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'text_id' => $this->text_id,
            'audio_filename' => basename($this->edit_audio_filename),
            'audio_url' => $this->edit_audio_url,
            'duration' => $this->edit_converted_audio_duration,
            'duration_seconds' => round($this->edit_converted_audio_duration / Audio::SAMPLE_RATE, 2),
            'split_status' => $this->split_status,
            'original_transcript' => $this->text?->edit_original_transcript,
            'normalized_transcript' => $this->text?->edit_normalized_transcript,
            'tokenized_transcript' => $this->text?->edit_tokenized_transcript,
        ];
    }
}
