<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Http\Controllers\Controller;
use App\Models\Text;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Verify - Speaking', weight: 100)]
class SpeakTextSkipController extends Controller
{
    /**
     * Пропустить текст, выданный для озвучивания.
     */
    public function __invoke(Request $request, Text $text): JsonResponse
    {
        if ($text->edit_speaker_id === $request->user()->id) {
            $text->update([
                'speak_started_at' => null,
                'edit_speaker_id' => null,
            ]);
        }

        return response()->json([
            'message' => 'Текст пропущен.',
        ]);
    }
}
