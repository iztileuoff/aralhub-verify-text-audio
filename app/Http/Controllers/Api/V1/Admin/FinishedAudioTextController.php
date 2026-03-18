<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\TextCollection;
use App\Models\Text;
use Illuminate\Http\Request;

class FinishedAudioTextController extends Controller
{
    public function __invoke(Request $request)
    {
        $texts = Text::query()
            ->whereNotNull('speak_finished_at')
            ->when($request->filled('file_id'), fn ($q) => $q->where('file_id', $request->input('file_id')))
            ->when($request->filled('speaker_id'), fn ($q) => $q->whereHas('audio', fn ($q) => $q->where('edit_speaker_id', $request->input('speaker_id'))))
            ->with(['editUser', 'audio.editSpeaker'])
            ->orderByDesc('speak_finished_at')
            ->paginate($request->input('per_page', 10));

        return new TextCollection($texts);
    }
}
