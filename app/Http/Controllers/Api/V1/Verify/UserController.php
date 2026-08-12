<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Verify\UserResource;
use App\Models\Audio;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group(name: 'Verify - Editing', weight: 90)]
class UserController extends Controller
{
    /**
     * Список исполнителей с дневной статистикой выполненной работы.
     */
    public function __invoke(Request $request)
    {
        $request->validate([
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'specialization_id' => ['sometimes', 'integer', 'exists:specializations,id'],
        ]);

        $date = $request->input('date', now()->format('Y-m-d'));

        /**
         * The sorting metric, counted once per speaker in a grouped join instead
         * of a correlated subquery per user: ordering by a subquery alias makes
         * MySQL evaluate every counter for every user before the LIMIT applies.
         */
        $speakAudioOnDate = Audio::query()
            ->selectRaw('edit_speaker_id, count(*) as speak_audio_count')
            ->mainFile()
            ->whereNotNull('edit_speaker_id')
            ->whereNotNull('speak_finished_at')
            ->onDate('speak_finished_at', $date)
            ->groupBy('edit_speaker_id');

        $users = User::query()
            ->select('users.*')
            ->leftJoinSub($speakAudioOnDate, 'speak_audio_on_date', 'speak_audio_on_date.edit_speaker_id', '=', 'users.id')
            ->where('role', '!=', RoleEnum::SUPER_ADMIN->value)
            ->when($request->filled('search'), fn ($q) => $q->search($request->input('search')))
            ->when($request->filled('specialization_id'), fn ($q) => $q->where('specialization_id', $request->input('specialization_id')))
            ->when(auth()->user()->role === RoleEnum::ADMIN, fn ($q) => $q->where('admin_id', auth()->user()->id))
            ->orderByDesc('speak_audio_on_date.speak_audio_count')
            ->orderByDesc('users.id')
            ->paginate($request->input('per_page', 10));

        $users->getCollection()->loadCount([
            'finishedEditTexts' => fn ($q) => $q->mainFile(),
            'finishedSpeakTexts' => fn ($q) => $q->mainFile(),
            'finishedModerationTexts' => fn ($q) => $q->mainFile(),
            'dateFinishedSpeakAudio' => fn ($q) => $q->mainFile()->onDate('speak_finished_at', $date),
            'dateFinishedModerationAudio' => fn ($q) => $q->mainFile()->onDate('moderator_finished_at', $date),
            'todayFinishedEditTexts' => fn ($q) => $q->mainFile()->onDate('edit_finished_at', $date),
        ]);

        return UserResource::collection($users);
    }
}
