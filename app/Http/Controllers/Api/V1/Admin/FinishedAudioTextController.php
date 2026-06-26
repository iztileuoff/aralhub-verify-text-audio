<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\TextResource;
use App\Models\Audio;
use App\Models\Text;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group(name: 'Texts', weight: 50)]
class FinishedAudioTextController extends Controller
{
    public function __invoke(Request $request)
    {
        $latestAudio = Audio::query()
            ->selectRaw('text_id, max(speak_finished_at) as audio_max_speak_finished_at')
            ->groupBy('text_id');

        $texts = Text::query()
            ->select('texts.*')
            ->leftJoinSub($latestAudio, 'latest_audio', 'latest_audio.text_id', '=', 'texts.id')
            ->when($request->filled('file_id'), fn ($q) => $q->where('texts.file_id', $request->input('file_id'))
            )
            ->when($request->filled('speaker_id'), fn ($q) => $q->whereHas('audio', fn ($q2) => $q2->where('edit_speaker_id', $request->input('speaker_id'))
            )
            )
            ->when($request->filled('search'), fn ($q) => $q->search($request->input('search')))
            ->with(['editUser', 'audio.editSpeaker'])
            ->orderByDesc('latest_audio.audio_max_speak_finished_at')
            ->paginate($request->input('per_page', 10));

        return TextResource::collection($texts);
    }
}
