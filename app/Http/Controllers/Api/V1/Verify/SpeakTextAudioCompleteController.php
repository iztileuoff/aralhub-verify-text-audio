<?php

namespace App\Http\Controllers\Api\V1\Verify;

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
            Storage::disk('public')->delete($text->edit_audio_filename);
        }

        if ($text->edit_converted_audio_filename) {
            Storage::disk('public')->delete($text->edit_converted_audio_filename);
        }

        $path = $request->file('audio')->store('audio', 'public');

        $text->edit_audio_filename = $path;
        $text->speak_finished_at = now();
        $text->edit_speaker_id = auth()->user()->id;
        $text->edit_speaker_gender = auth()->user()->gender;
        $text->save();

        ProcessAudioJob::dispatch($text->id, $path);

        return new TextResource($text);
    }
}
