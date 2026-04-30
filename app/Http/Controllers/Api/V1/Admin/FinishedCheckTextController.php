<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\AudioResource;
use App\Models\Audio;
use Illuminate\Http\Request;

class FinishedCheckTextController extends Controller
{
    public function __invoke(Request $request)
    {
        $audio = Audio::query()
            ->whereNotNull('is_correct')
            ->when($request->filled('speaker_id'), fn ($q) => $q->where('edit_speaker_id', $request->input('speaker_id')))
            ->when($request->filled('moderator_id'), fn ($q) => $q->where('moderator_id', $request->input('moderator_id')))
            ->when($request->filled('search'), fn ($q) => $q->search($request->input('search')))
            ->with(['text', 'editSpeaker', 'moderator'])
            ->orderByDesc('moderator_finished_at')
            ->paginate($request->input('per_page', 10));

        return AudioResource::collection($audio);
    }
}
