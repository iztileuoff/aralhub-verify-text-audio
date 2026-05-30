<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Http\Controllers\Controller;
use App\Models\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpeakTextErrorController extends Controller
{
    public function __invoke(Request $request, Text $text): JsonResponse
    {
        $text->update([
            'has_text_error' => true,
            'text_error_reported_by' => $request->user()->id,
            'text_error_reported_at' => now(),
            'speak_started_at' => null,
            'edit_speaker_id' => null,
        ]);

        return response()->json([
            'message' => 'Текст помечен как ошибочный.',
        ]);
    }
}
