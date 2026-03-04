<?php

namespace App\Http\Resources\V1\Admin;

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
            'filter_transcript' => $this->filter_transcript,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
