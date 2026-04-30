<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreSpecializationRequest;
use App\Http\Requests\Api\V1\Admin\UpdateSpecializationRequest;
use App\Http\Resources\V1\Admin\SpecializationResource;
use App\Models\Specialization;
use Illuminate\Http\Request;

class SpecializationController extends Controller
{
    public function index(Request $request)
    {
        $specializations = Specialization::query()
            ->get();

        return SpecializationResource::collection($specializations);
    }

    public function store(StoreSpecializationRequest $request)
    {
        return new SpecializationResource(Specialization::create($request->validated()));
    }

    public function show(Specialization $specialization)
    {
        return new SpecializationResource($specialization);
    }

    public function update(UpdateSpecializationRequest $request, Specialization $specialization)
    {
        $specialization->update($request->validated());

        return new SpecializationResource($specialization);
    }

    public function destroy(Specialization $specialization)
    {
        $specialization->delete();

        return response()->json();
    }
}
