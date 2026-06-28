<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\LongAudioRequest;
use App\Http\Resources\V1\Admin\LongAudioResource;
use App\Models\Audio;
use Dedoc\Scramble\Attributes\Group;

#[Group(name: 'Texts', weight: 50)]
class LongAudioController extends Controller
{
    public function __invoke(LongAudioRequest $request)
    {
        $audio = Audio::query()
            ->pendingSplit()
            ->with('text:id,edit_original_transcript,edit_normalized_transcript,edit_tokenized_transcript')
            ->orderBy('id', $request->input('direction', 'asc'))
            ->paginate($request->input('per_page') ?? 10);

        return LongAudioResource::collection($audio);
    }
}
