<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AudioSplitStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\SplitAudioRequest;
use App\Models\Audio;
use App\Models\Text;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Group(name: 'Texts', weight: 50)]
class LongAudioSplitController extends Controller
{
    /**
     * Принять 2 разрезанные части длинного аудио вместе с их частями текста.
     */
    public function __invoke(SplitAudioRequest $request, Audio $audio): JsonResponse
    {
        if (! $this->isPendingSplit($audio)) {
            return response()->json([
                'message' => 'Это аудио нельзя разрезать: оно короче лимита STT или уже обработано.',
            ], 422);
        }

        $parts = $request->validated('parts');

        $storedPaths = [];

        foreach ($parts as $part) {
            $path = $part['audio']->store('audio', 'yandex-s3');

            if ($path === false) {
                foreach ($storedPaths as $stored) {
                    Storage::disk('yandex-s3')->delete($stored);
                }

                return response()->json([
                    'message' => 'Не удалось загрузить аудио в хранилище. Попробуйте ещё раз.',
                ], 503);
            }

            $storedPaths[] = $path;
        }

        DB::transaction(function () use ($audio, $parts, $storedPaths): void {
            foreach ($parts as $index => $part) {
                $text = Text::create([
                    'file_id' => $audio->text?->file_id,
                    'edit_original_transcript' => $part['original_transcript'],
                    'edit_normalized_transcript' => $part['normalized_transcript'] ?? null,
                    'edit_tokenized_transcript' => $part['tokenized_transcript'] ?? null,
                ]);

                $audio->splitParts()->create([
                    'text_id' => $text->id,
                    'edit_audio_filename' => $storedPaths[$index],
                    'edit_converted_audio_duration' => $part['duration'],
                    'edit_speaker_id' => $audio->edit_speaker_id,
                    'edit_speaker_gender' => $audio->edit_speaker_gender,
                    'is_correct' => $audio->is_correct,
                    'moderator_id' => $audio->moderator_id,
                    'moderator_started_at' => $audio->moderator_started_at,
                    'moderator_finished_at' => $audio->moderator_finished_at,
                    'split_status' => AudioSplitStatusEnum::NONE,
                ]);
            }

            $audio->update(['split_status' => AudioSplitStatusEnum::SPLIT]);
        });

        return response()->json([
            'message' => 'Аудио разрезано на 2 части.',
        ], 201);
    }

    private function isPendingSplit(Audio $audio): bool
    {
        return $audio->edit_converted_audio_duration >= Audio::STT_MAX_DURATION_SAMPLES
            && ! in_array($audio->split_status, [
                AudioSplitStatusEnum::SPLIT,
                AudioSplitStatusEnum::UNSPLITTABLE,
            ], true);
    }
}
