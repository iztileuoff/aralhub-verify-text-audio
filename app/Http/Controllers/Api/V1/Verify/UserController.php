<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Verify\UserCollection;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __invoke(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));

        $users = User::query()
            ->where('role', '!=', RoleEnum::SUPER_ADMIN->value)
            ->when($request->filled('search'), fn ($q) => $q->search($request->input('search')))
            ->when($request->filled('specialization_id'), fn ($q) => $q->search($request->input('specialization_id')))
            ->when(auth()->user()->role === RoleEnum::ADMIN, fn ($q) => $q->where('admin_id', auth()->user()->id))
            ->withCount(['finishedEditTexts', 'finishedSpeakTexts', 'finishedModerationTexts'])
            ->withCount(['dateFinishedSpeakAudio' =>  fn ($q) => $q->whereDate('speak_finished_at', '=', $date)])
            ->withCount(['todayFinishedEditTexts' => fn ($q) => $q->whereDate('edit_finished_at', '=', $date)])
            ->withCount(['todayFinishedSpeakTexts' => fn ($q) => $q->whereDate('speak_finished_at', '=', $date)])
            ->withCount(['todayFinishedModerationTexts' => fn ($q) => $q->whereDate('moderator_finished_at', '=', $date)])
            ->orderBy('date_finished_speak_audio_count', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 10));

        return new UserCollection($users);
    }
}
