<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\TextResource;
use App\Models\Text;
use Illuminate\Http\Request;

class SpeakTextController extends Controller
{
    public function __invoke(Request $request)
    {
        $text = Text::query()
            ->whereNull('speak_finished_at')
            ->inRandomOrder()
            ->first();

        if (! $text) {
            return response()->json([
                'message' => 'Тексты для аудиозаписи отсутствуют.',
            ], 404);
        }

        $text->speak_started_at = now();
        $text->save();

        return new TextResource($text);
    }
}
