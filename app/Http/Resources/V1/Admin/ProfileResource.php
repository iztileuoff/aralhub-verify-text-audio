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
            'role_name' => $this->role->name,
            'name' => $this->name,
            'phone' => $this->phone,
            'password' => $this->password,
        ];
    }
}
