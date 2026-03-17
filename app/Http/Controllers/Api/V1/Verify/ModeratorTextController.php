<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\TextResource;
use App\Models\Text;
use Illuminate\Http\Request;

class ModeratorTextController extends Controller
{
    public function __invoke(Request $request)
    {
        $text = Text::query()
            ->where('moderator_id', $request->user()->id)
            ->whereNull('moderator_finished_at')
            ->first();

        if ($text) {
            $text->moderator_started_at = null;
            $text->moderator_id = null;
            $text->save();
        }

        $text = Text::query()
            ->whereNotNull('speak_finished_at')
            ->whereNull('moderator_started_at')
            ->whereNull('is_correct')
            ->first();

        if (! $text) {
            return response()->json([
                'message' => 'Тексты для аудиозаписи отсутствуют.',
            ], 404);
        }

        $text->moderator_started_at = now();
        $text->moderator_id = $request->user()->id;

        return new TextResource($text);
    }
}
