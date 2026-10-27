<?php

namespace App\Http\Resources\V1\Admin;

use App\Models\Audio;
use App\Models\CallLandmark;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CallLandmark */
class CallLandmarkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'examples_count' => $this->whenCounted('examples'),
            'mentions_count' => $this->whenCounted('calls'),
            'primary_call' => $this->whenLoaded('primaryCall', fn (): ?array => $this->primaryCall ? [
                'id' => $this->primaryCall->id,
                'trimmed_url' => $this->primaryCall->trimmedUrl(),
                'trimmed_duration_seconds' => $this->primaryCall->trimmed_duration === null
                    ? null
                    : round($this->primaryCall->trimmed_duration / Audio::SAMPLE_RATE, 2),
                'transcript' => $this->primaryCall->transcript,
            ] : null),
            'created_by' => $this->whenLoaded('creator', fn (): ?array => $this->creator ? [
                'id' => $this->creator->id,
                'first_name' => $this->creator->first_name,
                'last_name' => $this->creator->last_name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
