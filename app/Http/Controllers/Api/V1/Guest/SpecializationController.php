<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Guest\SpecializationCollection;
use App\Models\Specialization;
use Illuminate\Http\Request;

class SpecializationController extends Controller
{
    public function __invoke(Request $request)
    {
        $specializations = Specialization::query()
            ->get();

        return new SpecializationCollection($specializations);
    }
}
