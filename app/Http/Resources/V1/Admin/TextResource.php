<?php

namespace App\Http\Resources\V1\Admin;

use App\Enums\GenderEnum;
use App\Models\Text;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Text */
class TextResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_id' => $this->file_id,
            'transcript_id' => $this->transcript_id,
            'audio_filename' => $this->audio_filename,
            'original_transcript' => $this->original_transcript,
            'normalized_transcript' => $this->normalized_transcript,
            'tokenized_transcript' => $this->tokenized_transcript,
            'duration' => $this->duration,
            'speaker_gender' => $this->speaker_gender,
            'filter_original_transcript' => $this->filter_original_transcript,
            'filter_normalized_transcript' => $this->filter_normalized_transcript,
            'filter_tokenized_transcript' => $this->filter_tokenized_transcript,
            'edit_original_transcript' => $this->edit_original_transcript,
            'edit_normalized_transcript' => $this->edit_normalized_transcript,
            'edit_tokenized_transcript' => $this->edit_tokenized_transcript,
            'edit_user_id' => $this->edit_user_id,
            'edit_user' => new UserResource($this->whenLoaded('editUser')),
            'edit_started_at' => $this->edit_started_at?->format('Y-m-d H:i:s'),
            'edit_finished_at' => $this->edit_finished_at?->format('Y-m-d H:i:s'),
            'edit_cancelled_user_id' => $this->edit_cancelled_user_id,
            'edit_cancelled_user' => new UserResource($this->whenLoaded('editCancelledUser')),
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
            'moderator_id' => $this->moderator_id,
            'moderator' => new UserResource($this->whenLoaded('moderator')),
            'is_correct' => $this->is_correct,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
