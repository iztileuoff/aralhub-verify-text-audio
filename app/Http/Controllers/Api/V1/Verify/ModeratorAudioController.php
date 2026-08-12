<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\AudioResource;
use App\Models\Audio;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group(name: 'Verify - Moderation', weight: 110)]
class ModeratorAudioController extends Controller
{
    /**
     * Получить следующую аудиозапись для модерации.
     */
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
            ->mainFile()
            ->whereNotNull('speak_finished_at')
            ->whereNull('moderator_started_at')
            ->whereNull('is_correct')
            ->inRandomOrder()
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
