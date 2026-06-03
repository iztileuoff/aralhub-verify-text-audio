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
            'today_finished_edit_texts_count' => $this->whenHas('today_finished_edit_texts_count'),
            'finished_speak_texts_count' => $this->whenHas('finished_speak_texts_count'),
            'today_finished_speak_texts_count' => $this->whenHas('date_finished_speak_audio_count'),
            'finished_moderation_texts_count' => $this->whenHas('finished_moderation_texts_count'),
            'today_finished_moderation_texts_count' => $this->whenHas('date_finished_moderation_audio_count'),
            'is_verified' => $this->is_verified,
            'is_active' => $this->is_active,
            'limit_speak_audio' => 500,
            'limit_moderation_audio' => 500,
            'audio' => [
                'written_count' => $this->whenHas('finished_speak_audio_count'),
                'checked_count' => $this->whenHas('is_correct_true_audio_count', fn(): int => $this->is_correct_true_audio_count + $this->is_correct_false_audio_count),
                'correct_count' => $this->whenHas('is_correct_true_audio_count'),
                'incorrect_count' => $this->whenHas('is_correct_false_audio_count'),
            ],
            'moderation' => [
                'checked_count' => $this->whenHas('moderation_correct_audio_count', fn(): int => $this->moderation_correct_audio_count + $this->moderation_incorrect_audio_count),
                'correct_count' => $this->whenHas('moderation_correct_audio_count'),
                'incorrect_count' => $this->whenHas('moderation_incorrect_audio_count'),
            ],
        ];
    }
}
