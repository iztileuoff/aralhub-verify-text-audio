<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\TextResource;
use App\Models\Text;
use Illuminate\Http\Request;

class ModeratorTextCheckController extends Controller
{
    public function __invoke(Request $request, Text $text)
    {
        $request->validate([
            'is_correct' => ['required', 'boolean'],
        ]);

        $text->is_correct = $request->input('is_correct');
        $text->moderator_id = $request->user()->id;
        $text->moderator_finished_at = now();
        $text->save();

        return new TextResource($text);
    }
}
