<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateProfileRequest;
use App\Http\Resources\V1\Admin\ProfileResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group(name: 'Profile', weight: 20)]
class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $request->validate([
            'date' => ['sometimes', 'date_format:Y-m-d'],
        ]);

        $date = $request->input('date', now()->format('Y-m-d'));

        return new ProfileResource(auth()->user()->loadCount([
            'finishedEditTexts', 'finishedSpeakTexts', 'finishedModerationTexts',
            'finishedSpeakAudio', 'isCorrectTrueAudio', 'isCorrectFalseAudio',
        ])->loadCount([
            'dateFinishedSpeakAudio' => fn ($q) => $q->onDate('speak_finished_at', $date),
            'dateFinishedModerationAudio' => fn ($q) => $q->onDate('moderator_finished_at', $date),
            'todayFinishedEditTexts' => fn ($q) => $q->onDate('edit_finished_at', $date),
            'todayFinishedSpeakTexts' => fn ($q) => $q->onDate('speak_finished_at', $date),
            'todayFinishedModerationTexts' => fn ($q) => $q->onDate('moderator_finished_at', $date),
            'dateFinishedModerationAudio as moderation_correct_audio_count' => fn ($q) => $q->where('is_correct', true),
            'dateFinishedModerationAudio as moderation_incorrect_audio_count' => fn ($q) => $q->where('is_correct', false),
        ]));
    }

    public function update(UpdateProfileRequest $request)
    {
        return new ProfileResource(tap(auth()->user())->update($request->validated()));
    }
}
