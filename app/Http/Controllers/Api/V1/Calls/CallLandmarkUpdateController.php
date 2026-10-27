<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Calls\UpdateCallLandmarkRequest;
use App\Http\Resources\V1\Admin\CallLandmarkResource;
use App\Models\CallLandmark;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group(name: 'Calls - Landmarks', weight: 62)]
class CallLandmarkUpdateController extends Controller
{
    /**
     * Переименовать ориентир (исправить опечатку).
     */
    public function __invoke(UpdateCallLandmarkRequest $request, CallLandmark $landmark): JsonResponse
    {
        $name = $request->validated('name');
        $normalized = CallLandmark::normalize($name);

        $taken = CallLandmark::query()->where('normalized_name', $normalized)->whereKeyNot($landmark->id)->first();

        if ($taken) {
            return response()->json([
                'message' => "Ориентир «{$taken->name}» уже есть.",
                'errors' => ['name' => ["Ориентир «{$taken->name}» уже есть."]],
            ], 422);
        }

        $landmark->update(['name' => $name, 'normalized_name' => $normalized]);

        return (new CallLandmarkResource($landmark->load(['primaryCall', 'creator'])->loadCount(['examples', 'calls'])))->response();
    }
}
