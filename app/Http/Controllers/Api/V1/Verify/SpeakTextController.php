<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\TextResource;
use App\Models\Audio;
use App\Models\Text;
use Illuminate\Http\Request;

class SpeakTextController extends Controller
{
    public function __invoke(Request $request)
    {
        $text = Text::query()
            ->where('edit_speaker_id', auth()->user()->id)
            ->whereNotNull('speak_started_at')
            ->first();

        if ($text) {
            $text->speak_started_at = null;
            $text->edit_speaker_id = null;
            $text->save();
        }

        $query = Text::query()
            ->where('is_main', true)
            ->whereNotNull('edit_original_transcript')
            ->whereNull('speak_started_at')
            ->whereDoesntHave('audio', function ($q) {
                $q->where('edit_speaker_id', auth()->id());
            });

        // 1. Try only NULL
        $text = (clone $query)
            ->whereNull('audio_count')
            ->inRandomOrder()
            ->first();

        // 2. If none → fallback to < 3
        if (!$text) {
            $text = (clone $query)
                ->where('audio_count', '<', 3)
                ->inRandomOrder()
                ->first();
        }

        if (! $text) {
            return response()->json([
                'message' => 'Тексты для аудиозаписи отсутствуют.',
            ], 404);
        }

        $text->speak_started_at = now();
        $text->edit_speaker_id = auth()->user()->id;
        $text->save();

        return new TextResource($text);
    }
}
