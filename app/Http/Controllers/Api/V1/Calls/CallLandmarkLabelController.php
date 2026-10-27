<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Enums\CallLandmarkDecisionEnum;
use App\Http\Controllers\Concerns\DecidesCallLandmark;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Calls\LabelCallLandmarkRequest;
use App\Models\CallLandmark;
use App\Models\CallRecording;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;

#[Group(name: 'Calls - Landmarks', weight: 62)]
class CallLandmarkLabelController extends Controller
{
    use DecidesCallLandmark;

    /**
     * Новый ориентир этим звонком.
     *
     * `409` с `landmark` — такой ориентир уже есть (без учёта регистра, пробелов, ё/е):
     * звонок не меняется, фронт предлагает «Пропустить».
     */
    public function __invoke(LabelCallLandmarkRequest $request, CallRecording $call): JsonResponse
    {
        $name = $request->validated('name');
        $normalized = CallLandmark::normalize($name);
        $userId = $request->user()->id;

        try {
            return $this->decide($userId, $call, CallLandmarkDecisionEnum::LABELED, function (CallRecording $locked) use ($name, $normalized, $userId): int|CallLandmark {
                return CallLandmark::query()->where('normalized_name', $normalized)->first()
                    ?? CallLandmark::query()->create([
                        'name' => $name,
                        'normalized_name' => $normalized,
                        'call_recording_id' => $locked->id,
                        'created_by' => $userId,
                    ])->id;
            });
        } catch (UniqueConstraintViolationException) {
            // Another admin created the same landmark a moment earlier.
            return $this->alreadyExists(CallLandmark::query()->where('normalized_name', $normalized)->firstOrFail());
        }
    }
}
