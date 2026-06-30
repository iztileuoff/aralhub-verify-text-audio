<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Http\Controllers\Concerns\DerivesTranscripts;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\TextResource;
use App\Models\Action;
use App\Models\Text;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group(name: 'Verify - Editing', weight: 90)]
class TextUpdateController extends Controller
{
    use DerivesTranscripts;

    /**
     * Обновить уже отредактированный текст.
     */
    public function __invoke(Request $request, Text $text): TextResource
    {
        $request->validate([
            'text' => ['required', 'string'],
        ]);

        $newText = $request->input('text');
        $oldText = $text->edit_original_transcript;

        $text->fill($this->deriveTranscripts($newText))->save();

        Action::create([
            'text_id' => $text->id,
            'user_id' => $request->user()->id,
            'old_text' => $oldText,
            'new_text' => $newText,
        ]);

        return new TextResource($text);
    }
}
