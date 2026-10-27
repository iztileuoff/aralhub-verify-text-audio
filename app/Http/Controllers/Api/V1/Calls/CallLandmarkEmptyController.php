<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Enums\CallLandmarkDecisionEnum;
use App\Http\Controllers\Concerns\DecidesCallLandmark;
use App\Http\Controllers\Controller;
use App\Models\CallRecording;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Calls - Landmarks', weight: 62)]
class CallLandmarkEmptyController extends Controller
{
    use DecidesCallLandmark;

    /**
     * Ориентира нет (не о чем, тишина, непонятно).
     */
    public function __invoke(Request $request, CallRecording $call): JsonResponse
    {
        return $this->decide($request->user()->id, $call, CallLandmarkDecisionEnum::EMPTY, fn (): ?int => null);
    }
}
