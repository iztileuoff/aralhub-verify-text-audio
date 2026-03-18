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

        $text = Text::query()
            ->whereNotNull('edit_original_transcript')
            ->whereNull('speak_started_at')
            ->where('audio_count', '<', 3)
            ->whereDoesntHave('audio', function ($q) {
                $q->where('edit_speaker_id', auth()->user()->id);
            })
            ->inRandomOrder()
            ->first();

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
