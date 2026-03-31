<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\TextResource;
use App\Models\Audio;
use App\Models\Text;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpeakTextController extends Controller
{
    /**
     * @throws \Throwable
     */
    public function __invoke(Request $request)
    {
        return DB::transaction(function () {

            // release previous
            $text = Text::query()
                ->where('edit_speaker_id', auth()->id())
                ->whereNotNull('speak_started_at')
                ->lockForUpdate()
                ->first();

            $text?->update([
                'speak_started_at' => null,
                'edit_speaker_id' => null,
            ]);

            $baseQuery = Text::query()
                ->where('is_main', true)
                ->whereNotNull('edit_original_transcript')
                ->whereNull('speak_started_at')
                ->whereDoesntHave('audio', function ($q) {
                    $q->where('edit_speaker_id', auth()->id());
                });

            // 1. NULL priority
            $text = (clone $baseQuery)
                ->whereNull('audio_count')
                ->lockForUpdate()
                ->inRandomOrder()
                ->first();

            // 2. fallback < 3
            if (!$text) {
                $text = (clone $baseQuery)
                    ->where('audio_count', '<', 3)
                    ->lockForUpdate()
                    ->inRandomOrder()
                    ->first();
            }

            if (!$text) {
                return response()->json([
                    'message' => 'Тексты для аудиозаписи отсутствуют.',
                ], 404);
            }

            $text->update([
                'speak_started_at' => now(),
                'edit_speaker_id' => auth()->id(),
            ]);

            return new TextResource($text);
        });
    }
}
