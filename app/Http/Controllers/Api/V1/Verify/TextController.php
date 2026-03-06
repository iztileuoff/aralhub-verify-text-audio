<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\TextResource;
use App\Models\Text;
use Illuminate\Http\Request;

class TextController extends Controller
{
    public function __invoke(Request $request)
    {
        $text = Text::query()
            ->where('edit_user_id', $request->user()->id)
            ->whereNull('edit_finished_at')
            ->first();

        if ($text) {
            return new TextResource($text);
        }

        $text = Text::query()
            ->whereNotNull('filter_original_transcript')
            ->whereNull('edit_user_id')
            ->inRandomOrder()
            ->first();

        $text->edit_user_id = $request->user()->id;
        $text->edit_started_at = now();
        $text->save();

        return new TextResource($text);
    }
}
