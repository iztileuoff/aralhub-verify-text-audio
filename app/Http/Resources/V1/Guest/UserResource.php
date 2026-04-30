<?php

namespace App\Http\Resources\V1\Guest;

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
            'role_name' => $this->role->getLabelText(),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
        ];
    }
}
