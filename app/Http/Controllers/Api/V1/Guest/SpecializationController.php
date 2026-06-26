<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Guest\SpecializationResource;
use App\Models\Specialization;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group(name: 'Guest', weight: 120)]
class SpecializationController extends Controller
{
    public function __invoke(Request $request)
    {
        $specializations = Specialization::query()
            ->get();

        return SpecializationResource::collection($specializations);
    }
}
