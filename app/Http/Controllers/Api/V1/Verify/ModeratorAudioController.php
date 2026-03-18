<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\AudioResource;
use App\Http\Resources\V1\Admin\TextResource;
use App\Models\Audio;
use App\Models\Text;
use Illuminate\Http\Request;

class ModeratorAudioController extends Controller
{
    public function __invoke(Request $request)
    {
        $audio = Audio::query()
            ->where('moderator_id', $request->user()->id)
            ->whereNull('moderator_finished_at')
            ->first();

        if ($audio) {
            $audio->moderator_started_at = null;
            $audio->moderator_id = null;
            $audio->save();
        }

        $audio = Audio::query()
            ->whereNotNull('speak_finished_at')
            ->whereNull('moderator_started_at')
            ->whereNull('is_correct')
            ->first();

        if (! $audio) {
            return response()->json([
                'message' => 'Аудиозаписи отсутствуют.',
            ], 404);
        }

        $audio->moderator_started_at = now();
        $audio->moderator_id = $request->user()->id;

        return new AudioResource($audio->load('text', 'editSpeaker'));
    }
}
