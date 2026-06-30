<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AudioSplitStatusEnum;
use App\Http\Controllers\Concerns\DerivesTranscripts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateSplitPartTextRequest;
use App\Http\Resources\V1\Admin\TextResource;
use App\Models\Action;
use App\Models\Audio;
use App\Models\Text;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;

#[Group(name: 'Texts', weight: 50)]
class LongAudioPartTextUpdateController extends Controller
{
    use DerivesTranscripts;

    /**
     * Исправить текст одной из разрезанных частей длинного аудио.
     *
     * Правится только транскрипт части (аудио и оригинальный текст не трогаются):
     * иногда аудио разрезано верно, а текст поделён на части неправильно.
     */
    public function __invoke(UpdateSplitPartTextRequest $request, Text $text): TextResource
    {
        abort_unless($this->isEditableSplitPartText($text), 404);

        $newText = $request->validated('text');
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

    /**
     * A text is editable here only when it is a split-part child of a SPLIT
     * long audio in the main dataset — the very rows shown on the long-audio
     * results-review screen.
     */
    private function isEditableSplitPartText(Text $text): bool
    {
        return $text->is_split_part
            && $text->file_id === config('dataset.main_file_id')
            && Audio::query()
                ->where('text_id', $text->id)
                ->where('split_status', AudioSplitStatusEnum::NONE)
                ->whereHas('parent', fn (Builder $query): Builder => $query->where('split_status', AudioSplitStatusEnum::SPLIT))
                ->exists();
    }
}
