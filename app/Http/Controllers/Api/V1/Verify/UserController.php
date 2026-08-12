<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Verify\UserResource;
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

        $users = User::query()
            ->where('role', '!=', RoleEnum::SUPER_ADMIN->value)
            ->when($request->filled('search'), fn ($q) => $q->search($request->input('search')))
            ->when($request->filled('specialization_id'), fn ($q) => $q->where('specialization_id', $request->input('specialization_id')))
            ->when(auth()->user()->role === RoleEnum::ADMIN, fn ($q) => $q->where('admin_id', auth()->user()->id))
            ->withCount([
                'finishedEditTexts' => fn ($q) => $q->mainFile(),
                'finishedSpeakTexts' => fn ($q) => $q->mainFile(),
                'finishedModerationTexts' => fn ($q) => $q->mainFile(),
            ])
            ->withCount(['dateFinishedSpeakAudio' => fn ($q) => $q->mainFile()->onDate('speak_finished_at', $date)])
            ->withCount(['dateFinishedModerationAudio' => fn ($q) => $q->mainFile()->onDate('moderator_finished_at', $date)])
            ->withCount(['todayFinishedEditTexts' => fn ($q) => $q->mainFile()->onDate('edit_finished_at', $date)])
            ->withCount(['todayFinishedSpeakTexts' => fn ($q) => $q->mainFile()->onDate('speak_finished_at', $date)])
            ->withCount(['todayFinishedModerationTexts' => fn ($q) => $q->mainFile()->onDate('moderator_finished_at', $date)])
            ->orderBy('date_finished_speak_audio_count', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 10));

        return UserResource::collection($users);
    }
}
