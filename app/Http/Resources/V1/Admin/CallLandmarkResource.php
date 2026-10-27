<?php

namespace App\Http\Resources\V1\Admin;

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
            'calls_count' => $this->whenCounted('calls'),
            'primary_call_id' => $this->call_recording_id,
            'created_by' => $this->whenLoaded('creator', fn (): ?array => $this->creator ? [
                'id' => $this->creator->id,
                'first_name' => $this->creator->first_name,
                'last_name' => $this->creator->last_name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
