<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Enums\GenderEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Verify\StoreSpeakTextRequest;
use App\Http\Resources\V1\Admin\TextResource;
use App\Models\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SpeakTextAudioCompleteController extends Controller
{
    public function __invoke(StoreSpeakTextRequest $request, Text $text): JsonResponse
    {
        try {
            $path = $request->file('audio')->store('audio', 'yandex-s3');
        } catch (Throwable $exception) {
            report($exception);
            $path = false;
        }

        if ($path === false) {
            Log::error('Audio upload to Yandex S3 failed', [
                'text_id' => $text->id,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Не удалось загрузить аудио в хранилище. Попробуйте ещё раз.',
            ], 503);
        }

        if ($text->edit_audio_filename) {
            Storage::disk('yandex-s3')->delete($text->edit_audio_filename);
        }

        $text->audio()->create([
            'edit_audio_filename' => $path,
            'speak_started_at' => $text->speak_started_at,
            'speak_finished_at' => now(),
            'edit_speaker_id' => auth()->user()->id,
            'edit_speaker_gender' => auth()->user()->gender,
        ]);

        $text->speak_started_at = null;
        $text->edit_speaker_id = null;
        $text->audio_count = $text->audio()->count();
        $text->audio_male_count = $text->audio()->where('edit_speaker_gender', GenderEnum::MALE->value)->count();
        $text->audio_female_count = $text->audio()->where('edit_speaker_gender', GenderEnum::FEMALE->value)->count();

        $text->save();

        return (new TextResource($text))->response();
    }
}
