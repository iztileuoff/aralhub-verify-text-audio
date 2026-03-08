<?php

namespace App\Http\Resources\V1\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class ProfileResource extends JsonResource
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
            'course' => $this->whenLoaded('course'),
            'finished_edit_texts_count' => $this->whenHas('finished_edit_texts_count'),
            'is_verified' => $this->is_verified,
            'is_active' => $this->is_active,
        ];
    }
}
