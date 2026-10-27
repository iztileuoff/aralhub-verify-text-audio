<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Enums\CallLandmarkDecisionEnum;
use App\Http\Controllers\Concerns\DecidesCallLandmark;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Calls\DuplicateCallLandmarkRequest;
use App\Models\CallRecording;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group(name: 'Calls - Landmarks', weight: 62)]
class CallLandmarkDuplicateController extends Controller
{
    use DecidesCallLandmark;

    /**
     * Пропустить: ориентир уже есть — звонок привязывается к нему.
     */
    public function __invoke(DuplicateCallLandmarkRequest $request, CallRecording $call): JsonResponse
    {
        $landmarkId = (int) $request->validated('landmark_id');

        return $this->decide($request->user()->id, $call, CallLandmarkDecisionEnum::DUPLICATE, fn (): int => $landmarkId);
    }
}
