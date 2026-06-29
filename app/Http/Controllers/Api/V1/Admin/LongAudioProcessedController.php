<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AudioSplitStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\LongAudioProcessedRequest;
use App\Http\Resources\V1\Admin\LongAudioResource;
use App\Models\Audio;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group(name: 'Texts', weight: 50)]
class LongAudioProcessedController extends Controller
{
    /**
     * Список уже обработанных длинных аудио: разрезанные (с их 2 частями) и помеченные неразрезаемыми — для проверки результата.
     */
    public function __invoke(LongAudioProcessedRequest $request): AnonymousResourceCollection
    {
        $transcriptColumns = 'id,edit_original_transcript,edit_normalized_transcript,edit_tokenized_transcript';

        $audio = Audio::query()
            ->whereIn('split_status', $this->statuses($request->input('status')))
            ->whereHas('text', fn (Builder $query): Builder => $query->where('file_id', config('dataset.main_file_id')))
            ->with([
                'text:'.$transcriptColumns,
                'splitParts:id,parent_audio_id,text_id,edit_audio_filename,edit_converted_audio_duration,split_status',
                'splitParts.text:'.$transcriptColumns,
            ])
            ->orderBy('id', $request->input('direction', 'asc'))
            ->paginate($request->input('per_page') ?? 10);

        return LongAudioResource::collection($audio);
    }

    /**
     * @return array<int, AudioSplitStatusEnum>
     */
    private function statuses(?string $status): array
    {
        return match ($status) {
            'split' => [AudioSplitStatusEnum::SPLIT],
            'unsplittable' => [AudioSplitStatusEnum::UNSPLITTABLE],
            default => [AudioSplitStatusEnum::SPLIT, AudioSplitStatusEnum::UNSPLITTABLE],
        };
    }
}
