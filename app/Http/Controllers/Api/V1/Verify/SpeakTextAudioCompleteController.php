<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Enums\GenderEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Verify\StoreSpeakTextRequest;
use App\Http\Resources\V1\Admin\TextResource;
use App\Jobs\ProcessAudioJob;
use App\Models\Text;
use Illuminate\Support\Facades\Storage;

class SpeakTextAudioCompleteController extends Controller
{
    public function __invoke(StoreSpeakTextRequest $request, Text $text)
    {
        if ($text->edit_audio_filename) {
            Storage::disk('yandex-s3')->delete($text->edit_audio_filename);
        }

        //        if ($text->edit_converted_audio_filename) {
        //            Storage::disk('yandex-s3')->delete($text->edit_converted_audio_filename);
        //        }

        $path = $request->file('audio')->store('audio', 'yandex-s3');

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

        //        ProcessAudioJob::dispatch($text->id, $path);

        return new TextResource($text);
    }
}
