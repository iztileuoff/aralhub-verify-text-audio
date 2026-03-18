<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateProfileRequest;
use App\Models\Audio;
use App\Models\Text;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DailyQuotaController extends Controller
{
    public function show(Request $request)
    {
        return response()->json([
            'data' => $this->getDailyQuota(),
        ]);
    }

    public function update(UpdateProfileRequest $request)
    {
        Cache::forget('daily_quota_texts_data');
        Cache::forget('daily_quota_audios_data');
        Cache::forget('daily_quota_check_audios_data');

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
            ->where('is_active', true)
            ->count();

        $activeSpeakersCount  = User::query()
            ->where('role', RoleEnum::SPEAKER->value)
            ->where('is_active', true)
            ->count();

        $activeModeratorsCount = User::query()
            ->where('role', RoleEnum::MODERATOR->value)
            ->where('is_active', true)
            ->count();

        $audioFinishedTextsCount = Audio::query()
            ->whereNotNull('speak_finished_at')
            ->count();

        $moderatorFinishedAudiosCount = Text::query()
            ->whereNotNull('is_correct')
            ->count();

        $moderatorNotFinishedAudiosCount = Text::query()
            ->whereNull('is_correct')
            ->whereNotNull('speak_finished_at')
            ->count();

        $dailyQuotaTextsCount = Cache::rememberForever('daily_quota_texts_data', function () {
            return Text::query()
                ->whereNull('edit_finished_at')
                ->orWhere('edit_finished_at', '>=', today())
                ->count();
        });

        $dailyQuotaAudiosCount = Cache::rememberForever('daily_quota_audios_data', function () {
            return Text::query()
                ->whereNull('speak_finished_at')
                ->orWhere('speak_finished_at', '>=', today())
                ->count();
        });

        $dailyQuotaCheckAudiosCount = Cache::rememberForever('daily_quota_check_audios_data', function () {
            return Text::query()
                ->whereNotNull('speak_finished_at')
                ->whereNull('moderator_finished_at')
                ->orWhere('moderator_finished_at', '>=', today())
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
            'audio_not_finished_texts_count' => $editFinishedTextsCount * 3 - $audioFinishedTextsCount,
            'daily_quota_audios_count' => $dailyQuotaAudiosCount,
            'moderator_finished_audios_count' => $moderatorFinishedAudiosCount,
            'moderator_not_finished_audios_count' => $moderatorNotFinishedAudiosCount,
            'daily_quota_check_audios_count' => $dailyQuotaCheckAudiosCount,
        ];
    }
}
