<?php

namespace App\Http\Resources\V1\Guest;

use App\Models\Specialization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** @see Specialization */
class SpecializationCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}
