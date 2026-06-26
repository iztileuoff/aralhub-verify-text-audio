<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\TextResource;
use App\Models\Text;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group(name: 'Verify - Editing', weight: 90)]
class TextCancelController extends Controller
{
    /**
     * Отменить редактирование и освободить текст для других редакторов.
     */
    public function __invoke(Request $request, Text $text)
    {
        $text->edit_user_id = null;
        $text->edit_started_at = null;
        $text->edit_finished_at = null;
        $text->edit_cancelled_user_id = $request->user()->id;
        $text->save();

        return new TextResource($text);
    }
}
