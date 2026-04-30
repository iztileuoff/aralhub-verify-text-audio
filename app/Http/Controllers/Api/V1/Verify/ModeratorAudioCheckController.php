<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\AudioResource;
use App\Models\Audio;
use Illuminate\Http\Request;

class ModeratorAudioCheckController extends Controller
{
    public function __invoke(Request $request, Audio $audio)
    {
        $request->validate([
            'is_correct' => ['required', 'boolean'],
        ]);

        $audio->is_correct = $request->input('is_correct');
        $audio->moderator_id = $request->user()->id;
        $audio->moderator_finished_at = now();
        $audio->save();

        return new AudioResource($audio);
    }
}
