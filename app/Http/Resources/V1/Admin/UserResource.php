<?php

namespace App\Http\Resources\V1\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'role_name' => $this->role->getLabelText(),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'age' => $this->age,
            'specialization_id' => $this->specialization_id,
            'specialization' => new SpecializationResource($this->whenLoaded('specialization')),
            'course' => $this->course,
            'is_verified' => $this->is_verified,
            'is_active' => $this->is_active,
            'finished_edit_audio_count' => $this->whenHas('finished_speak_audio_count'),
            'is_correct_true_audio_count' => $this->whenHas('is_correct_true_audio_count'),
            'is_correct_false_audio_count' => $this->whenHas('is_correct_false_audio_count'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
