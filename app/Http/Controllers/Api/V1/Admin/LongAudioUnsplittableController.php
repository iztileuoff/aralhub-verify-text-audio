<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AudioSplitStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Audio;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group(name: 'Texts', weight: 50)]
class LongAudioUnsplittableController extends Controller
{
    /**
     * Пометить длинное аудио как неразрезаемое и вывести его из STT-потока.
     */
    public function __invoke(Audio $audio): JsonResponse
    {
        if ($audio->split_status === AudioSplitStatusEnum::SPLIT) {
            return response()->json([
                'message' => 'Аудио уже разрезано.',
            ], 422);
        }

        $audio->update(['split_status' => AudioSplitStatusEnum::UNSPLITTABLE]);

        return response()->json([
            'message' => 'Аудио помечено как неразрезаемое.',
        ]);
    }
}
