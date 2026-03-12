<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateProfileRequest;
use App\Models\Text;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DailyQuotaController extends Controller
{
    private const CACHE_KEY = 'daily_quota_data';

    public function show(Request $request)
    {
        return response()->json([
            'data' => $this->getDailyQuota(),
        ]);
    }

    public function update(UpdateProfileRequest $request)
    {
        Cache::forget(self::CACHE_KEY);

        return response()->json([
            'data' => $this->getDailyQuota(),
        ]);
    }

    private function getDailyQuota(): array
    {
        $textsCount = Text::query()
            ->count();

        $editFinishedTextsCount = Text::query()
            ->whereNotNull('edit_finished_at')
            ->count();

        $editNotFinishedTextsCount = Text::query()
            ->whereNull('edit_finished_at')
            ->count();

        $usersCount = User::query()
            ->whereIn('role', [RoleEnum::EDITOR->value, RoleEnum::SPEAKER->value, RoleEnum::MODERATOR->value])
            ->count();

        $activeEditorsCount = User::query()
            ->where('role', RoleEnum::EDITOR->value)
            ->count();

        $activeSpeakersCount  = User::query()
            ->where('role', RoleEnum::SPEAKER->value)
            ->count();

        $activeModeratorsCount = User::query()
            ->where('role', RoleEnum::MODERATOR->value)
            ->count();

        $audioFinishedTextsCount = Text::query()
            ->whereNotNull('speak_finished_at')
            ->count();

        $moderatorFinishedAudiosCount = Text::query()
            ->whereNotNull('is_correct')
            ->count();

        $moderatorNotFinishedAudiosCount = Text::query()
            ->whereNull('is_correct')
            ->whereNotNull('speak_finished_at')
            ->count();

        $dailyQuotaTextsCount = Cache::rememberForever(self::CACHE_KEY, function () {
            return Text::query()
                ->whereNull('edit_finished_at')
                ->orWhere('edit_finished_at', '>=', today())
                ->count();
        });

        return [
            'users_count' => $usersCount,
            'active_editors_count' => $activeEditorsCount,
            'active_speaker_count' => $activeSpeakersCount,
            'active_moderators_count' => $activeModeratorsCount,
            'texts_count' => $textsCount,
            'edit_finished_texts_count' => $editFinishedTextsCount,
            'edit_not_finished_texts_count' => $editNotFinishedTextsCount,
            'daily_quota_texts_count' => $dailyQuotaTextsCount,
            'audio_finished_texts_count' => $audioFinishedTextsCount,
            'audio_not_finished_texts_count' => $editFinishedTextsCount - $audioFinishedTextsCount,
            'moderator_finished_audios_count' => $moderatorFinishedAudiosCount,
            'moderator_not_finished_audios_count' => $moderatorNotFinishedAudiosCount,
            'quota_per_user' => $usersCount ? intdiv($dailyQuotaTextsCount, $usersCount) : 0,
        ];
    }
}
