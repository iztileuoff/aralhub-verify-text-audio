<?php

namespace App\Http\Resources\V1\Admin;

use App\Models\Audio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Audio */
class AudioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'text_id' => $this->text_id,
            'text' => new TextResource($this->whenLoaded('text')),
            'edit_audio_filename' => $this->edit_audio_filename,
            'edit_audio_url' => $this->edit_audio_url,
            'edit_converted_audio_filename' => $this->edit_converted_audio_filename,
            'edit_converted_audio_url' => $this->edit_converted_audio_url,
            'edit_converted_audio_duration' => $this->edit_converted_audio_duration,
            'speak_started_at' => $this->speak_started_at?->format('Y-m-d H:i:s'),
            'speak_finished_at' => $this->speak_finished_at?->format('Y-m-d H:i:s'),
            'edit_speaker_id' => $this->edit_speaker_id,
            'edit_speaker' => new UserResource($this->whenLoaded('editSpeaker')),
            'edit_speaker_gender' => $this->edit_speaker_gender,
        ];
    }
}
