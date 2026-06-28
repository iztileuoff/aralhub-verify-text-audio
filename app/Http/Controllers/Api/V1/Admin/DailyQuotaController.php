<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateProfileRequest;
use App\Models\Audio;
use App\Models\Text;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

#[Group(name: 'Dashboard', weight: 30)]
class DailyQuotaController extends Controller
{
    private const CACHE_KEY = 'daily_quota_data';

    private const CACHE_FRESH_SECONDS = 3600;

    private const CACHE_STALE_SECONDS = 7200;

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => $this->getDailyQuota(),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        Cache::forget(self::CACHE_KEY);

        return response()->json([
            'data' => $this->getDailyQuota(),
        ]);
    }

    /**
     * @return array<string, int|float|string>
     */
    private function getDailyQuota(): array
    {
        return Cache::flexible(
            self::CACHE_KEY,
            [self::CACHE_FRESH_SECONDS, self::CACHE_STALE_SECONDS],
            fn (): array => $this->buildDailyQuota(),
        );
    }

    /**
     * @return array<string, int|float|string>
     */
    private function buildDailyQuota(): array
    {
        $today = today();
        $mainFileId = config('dataset.main_file_id');

        $userStats = User::query()
            ->selectRaw(
                'SUM(CASE WHEN role = ? THEN 1 ELSE 0 END) as speakers_count, '.
                'SUM(CASE WHEN role IN (?, ?, ?) THEN 1 ELSE 0 END) as users_count, '.
                'SUM(CASE WHEN role = ? AND is_active = 1 THEN 1 ELSE 0 END) as active_editors_count, '.
                'SUM(CASE WHEN role = ? AND is_active = 1 THEN 1 ELSE 0 END) as active_speaker_count, '.
                'SUM(CASE WHEN role = ? AND is_active = 1 THEN 1 ELSE 0 END) as active_moderators_count',
                [
                    RoleEnum::SPEAKER->value,
                    RoleEnum::EDITOR->value, RoleEnum::SPEAKER->value, RoleEnum::MODERATOR->value,
                    RoleEnum::EDITOR->value,
                    RoleEnum::SPEAKER->value,
                    RoleEnum::MODERATOR->value,
                ]
            )
            ->first();

        $textStats = Text::query()
            ->excludingSplitParts()
            ->selectRaw(
                'SUM(CASE WHEN is_main = 1 AND file_id = ? THEN 1 ELSE 0 END) as texts_count, '.
                'SUM(CASE WHEN is_main = 1 AND file_id = ? AND has_text_error = 1 THEN 1 ELSE 0 END) as error_texts_count, '.
                'SUM(CASE WHEN is_main = 1 AND file_id = ? THEN audio_count ELSE 0 END) as audio_finished_texts_count, '.
                'SUM(CASE WHEN edit_finished_at IS NOT NULL THEN 1 ELSE 0 END) as edit_finished_texts_count, '.
                'SUM(CASE WHEN edit_finished_at IS NULL THEN 1 ELSE 0 END) as edit_not_finished_texts_count, '.
                'SUM(CASE WHEN edit_finished_at IS NULL OR edit_finished_at >= ? THEN 1 ELSE 0 END) as daily_quota_texts_count, '.
                'SUM(CASE WHEN speak_finished_at IS NULL OR speak_finished_at >= ? THEN 1 ELSE 0 END) as daily_quota_audios_count, '.
                'SUM(CASE WHEN speak_finished_at IS NOT NULL AND (moderator_finished_at IS NULL OR moderator_finished_at >= ?) THEN 1 ELSE 0 END) as daily_quota_check_audios_count, '.
                'SUM(CASE WHEN edit_finished_at >= ? THEN 1 ELSE 0 END) as edit_finished_today_count',
                [$mainFileId, $mainFileId, $mainFileId, $today, $today, $today, $today]
            )
            ->first();

        $audioMainStats = Audio::query()
            ->whereHas('text', fn (Builder $query) => $query->mainFile())
            ->selectRaw(
                'COUNT(*) as total_audios_count, '.
                'COALESCE(SUM(edit_converted_audio_duration), 0) as total_audio_duration_seconds, '.
                'SUM(CASE WHEN is_correct IS NOT NULL THEN 1 ELSE 0 END) as moderator_finished_audios_count, '.
                'SUM(CASE WHEN is_correct IS NULL AND speak_finished_at IS NOT NULL THEN 1 ELSE 0 END) as moderator_not_finished_audios_count'
            )
            ->first();

        $audioTodayStats = Audio::query()
            ->selectRaw(
                'SUM(CASE WHEN speak_finished_at >= ? THEN 1 ELSE 0 END) as speak_finished_today_count, '.
                'SUM(CASE WHEN moderator_finished_at >= ? THEN 1 ELSE 0 END) as moderator_finished_today_count',
                [$today, $today]
            )
            ->first();

        $textsCount = (int) $textStats->texts_count;
        $errorTextsCount = (int) $textStats->error_texts_count;
        $audioFinishedTextsCount = (int) $textStats->audio_finished_texts_count;
        $totalAudioDurationSeconds = (int) $audioMainStats->total_audio_duration_seconds;

        return [
            'speakers_count' => (int) $userStats->speakers_count,
            'users_count' => (int) $userStats->users_count,
            'active_editors_count' => (int) $userStats->active_editors_count,
            'active_speaker_count' => (int) $userStats->active_speaker_count,
            'active_moderators_count' => (int) $userStats->active_moderators_count,
            'texts_count' => $textsCount,
            'error_texts_count' => $errorTextsCount,
            'edit_finished_texts_count' => (int) $textStats->edit_finished_texts_count,
            'edit_not_finished_texts_count' => (int) $textStats->edit_not_finished_texts_count,
            'daily_quota_texts_count' => (int) $textStats->daily_quota_texts_count,
            'audio_finished_texts_count' => $audioFinishedTextsCount,
            'audio_not_finished_texts_count' => $textsCount - $audioFinishedTextsCount,
            'daily_quota_audios_count' => (int) $textStats->daily_quota_audios_count,
            'moderator_finished_audios_count' => (int) $audioMainStats->moderator_finished_audios_count,
            'moderator_not_finished_audios_count' => (int) $audioMainStats->moderator_not_finished_audios_count,
            'daily_quota_check_audios_count' => (int) $textStats->daily_quota_check_audios_count,
            'edit_finished_today_count' => (int) $textStats->edit_finished_today_count,
            'speak_finished_today_count' => (int) $audioTodayStats->speak_finished_today_count,
            'moderator_finished_today_count' => (int) $audioTodayStats->moderator_finished_today_count,
            'total_audios_count' => (int) $audioMainStats->total_audios_count,
            'total_audio_duration_seconds' => $totalAudioDurationSeconds,
            'total_audio_duration_hours' => round($totalAudioDurationSeconds / 3600, 2),
            'error_texts_percent' => $textsCount > 0 ? round($errorTextsCount / $textsCount * 100, 2) : 0.0,
            'audio_progress_percent' => $textsCount > 0 ? round($audioFinishedTextsCount / $textsCount * 100, 2) : 0.0,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
