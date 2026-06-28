<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\TextResource;
use App\Models\Text;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group(name: 'Verify - Editing', weight: 90)]
class TextController extends Controller
{
    /**
     * Получить следующий текст для редактирования. Возвращает незавершённый текст редактора либо назначает новый случайный.
     */
    public function __invoke(Request $request)
    {
        $text = Text::query()
            ->where('edit_user_id', $request->user()->id)
            ->whereNull('edit_finished_at')
            ->first();

        if ($text) {
            $text->edit_started_at = now();
            $text->save();

            return new TextResource($text);
        }

        $text = Text::query()
            ->excludingSplitParts()
            ->whereNotNull('filter_original_transcript')
            ->whereNull('edit_user_id')
            ->inRandomOrder()
            ->first();

        if (! $text) {
            return response()->json([
                'message' => 'Нет доступных текстов для редактирования.',
            ], 404);
        }

        $text->edit_user_id = $request->user()->id;
        $text->edit_started_at = now();
        $text->save();

        return new TextResource($text);
    }
}
