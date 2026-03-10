<?php

namespace App\Http\Resources\V1\Admin;

use App\Models\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Action */
class ActionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'old_text' => $this->old_text,
            'new_text' => $this->new_text,
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'text_id' => $this->text_id,
            'text' => new TextResource($this->whenLoaded('text')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
