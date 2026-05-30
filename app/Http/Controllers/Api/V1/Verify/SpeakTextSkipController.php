<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Http\Controllers\Controller;
use App\Models\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpeakTextSkipController extends Controller
{
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
