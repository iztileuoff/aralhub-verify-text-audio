<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Verify\StoreSpeakTextRequest;
use App\Http\Resources\V1\Admin\TextResource;
use App\Jobs\UploadSpeakAudioToYandex;
use App\Models\Text;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

#[Group(name: 'Verify - Speaking', weight: 100)]
class SpeakTextAudioCompleteController extends Controller
{
    /**
     * Сохранить записанное аудио для текста.
     *
     * Файл буферизуется локально, после чего заливка в Yandex S3 и создание
     * записи Audio выполняются в очереди, чтобы не держать HTTP-запрос на
     * время сетевой загрузки. Запись Audio появляется только после успешной
     * заливки в хранилище.
     */
    public function __invoke(StoreSpeakTextRequest $request, Text $text): JsonResponse
    {
        $filename = $request->file('audio')->store('audio', 'local');

        if ($filename === false) {
            Log::error('Audio upload to local storage failed', [
                'text_id' => $text->id,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Не удалось загрузить аудио в хранилище. Попробуйте ещё раз.',
            ], 503);
        }

        $speaker = auth()->user();

        UploadSpeakAudioToYandex::dispatch(
            textId: $text->id,
            filename: $filename,
            previousFilename: $text->edit_audio_filename,
            speakerId: $speaker->id,
            speakerGender: $speaker->gender?->value,
            speakStartedAt: $text->speak_started_at?->toDateTimeString(),
            speakFinishedAt: now()->toDateTimeString(),
        );

        $text->speak_started_at = null;
        $text->edit_speaker_id = null;
        $text->save();

        return (new TextResource($text))->response();
    }
}
