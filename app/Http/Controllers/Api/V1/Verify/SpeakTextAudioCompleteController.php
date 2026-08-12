<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Enums\GenderEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Verify\StoreSpeakTextRequest;
use App\Http\Resources\V1\Admin\TextResource;
use App\Models\Text;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Group(name: 'Verify - Speaking', weight: 100)]
class SpeakTextAudioCompleteController extends Controller
{
    /**
     * Сохранить записанное аудио для текста.
     */
    public function __invoke(StoreSpeakTextRequest $request, Text $text): JsonResponse
    {
        // One speaker, one recording per text. The upload takes up to a minute,
        // so clients resend it after a timeout or a double tap; answering such a
        // retry with the saved state keeps the app happy without a second take.
        if ($this->recordedBy($text, auth()->id())) {
            return (new TextResource($text))->response();
        }

        $disk = config('audio.disk');

        try {
            $path = $request->file('audio')->store('audio', $disk);
        } catch (Throwable $exception) {
            report($exception);
            $path = false;
        }

        if ($path === false) {
            Log::error('Audio upload to object storage failed', [
                'disk' => $disk,
                'text_id' => $text->id,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Не удалось загрузить аудио в хранилище. Попробуйте ещё раз.',
            ], 503);
        }

        $saved = DB::transaction(function () use ($text, $path, $disk): bool {
            // Two retries can be in flight at once, both past the check above;
            // the row lock lets only one of them through.
            Text::query()->whereKey($text->getKey())->lockForUpdate()->first();

            if ($this->recordedBy($text, auth()->id())) {
                return false;
            }

            $text->audio()->create([
                'edit_audio_filename' => $path,
                'storage_disk' => $disk,
                'speak_started_at' => $text->speak_started_at,
                'speak_finished_at' => now(),
                'edit_speaker_id' => auth()->user()->id,
                'edit_speaker_gender' => auth()->user()->gender,
            ]);

            return true;
        });

        if (! $saved) {
            Storage::disk($disk)->delete($path);

            return (new TextResource($text))->response();
        }

        if ($text->edit_audio_filename) {
            Storage::disk($text->audioDisk())->delete($text->edit_audio_filename);
        }

        $text->speak_started_at = null;
        $text->edit_speaker_id = null;
        $text->audio_count = $text->audio()->count();
        $text->audio_male_count = $text->audio()->where('edit_speaker_gender', GenderEnum::MALE->value)->count();
        $text->audio_female_count = $text->audio()->where('edit_speaker_gender', GenderEnum::FEMALE->value)->count();

        $text->save();

        return (new TextResource($text))->response();
    }

    /**
     * Whether this speaker already has a recording of this text.
     */
    private function recordedBy(Text $text, int $speakerId): bool
    {
        return $text->audio()->where('edit_speaker_id', $speakerId)->exists();
    }
}
