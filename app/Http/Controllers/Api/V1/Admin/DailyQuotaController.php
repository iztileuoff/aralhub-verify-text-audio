<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateProfileRequest;
use App\Models\Audio;
use App\Models\Text;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DailyQuotaController extends Controller
{
    private const CACHE_KEY = 'daily_quota_data';

    private const CACHE_TTL_MINUTES = 60;

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
        return Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn (): array => $this->buildDailyQuota(),
        );
    }

    /**
     * @return array<string, int|float|string>
     */
    private function buildDailyQuota(): array
    {
        $today = today();

        $speakersCount = User::query()
            ->where('role', RoleEnum::SPEAKER->value)
            ->count();

        $usersCount = User::query()
            ->whereIn('role', [RoleEnum::EDITOR->value, RoleEnum::SPEAKER->value, RoleEnum::MODERATOR->value])
            ->count();

        $activeEditorsCount = User::query()
            ->where('role', RoleEnum::EDITOR->value)
            ->where('is_active', true)
            ->count();

        $activeSpeakersCount = User::query()
            ->where('role', RoleEnum::SPEAKER->value)
            ->where('is_active', true)
            ->count();

        $activeModeratorsCount = User::query()
            ->where('role', RoleEnum::MODERATOR->value)
            ->where('is_active', true)
            ->count();

        $textsCount = Text::query()->mainFile()->count();

        $errorTextsCount = Text::query()
            ->mainFile()
            ->where('has_text_error', true)
            ->count();

        $editFinishedTextsCount = Text::query()
            ->whereNotNull('edit_finished_at')
            ->count();

        $editNotFinishedTextsCount = Text::query()
            ->whereNull('edit_finished_at')
            ->count();

        $audioFinishedTextsCount = (int) Text::query()->mainFile()->sum('audio_count');

        $audioNotFinishedTextsCount = $textsCount - $audioFinishedTextsCount;

        $moderatorFinishedAudiosCount = Audio::query()
            ->whereHas('text', fn (Builder $query) => $query->mainFile())
            ->whereNotNull('is_correct')
            ->count();

        $moderatorNotFinishedAudiosCount = Audio::query()
            ->whereHas('text', fn (Builder $query) => $query->mainFile())
            ->whereNull('is_correct')
            ->whereNotNull('speak_finished_at')
            ->count();

        $dailyQuotaTextsCount = Text::query()
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('edit_finished_at')
                    ->orWhere('edit_finished_at', '>=', $today);
            })
            ->count();

        $dailyQuotaAudiosCount = Text::query()
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('speak_finished_at')
                    ->orWhere('speak_finished_at', '>=', $today);
            })
            ->count();

        $dailyQuotaCheckAudiosCount = Text::query()
            ->whereNotNull('speak_finished_at')
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('moderator_finished_at')
                    ->orWhere('moderator_finished_at', '>=', $today);
            })
            ->count();

        $editFinishedTodayCount = Text::query()
            ->where('edit_finished_at', '>=', $today)
            ->count();

        $speakFinishedTodayCount = Audio::query()
            ->where('speak_finished_at', '>=', $today)
            ->count();

        $moderatorFinishedTodayCount = Audio::query()
            ->where('moderator_finished_at', '>=', $today)
            ->count();

        $totalAudiosCount = Audio::query()
            ->whereHas('text', fn (Builder $query) => $query->mainFile())
            ->count();

        $totalAudioDurationSeconds = (int) Audio::query()
            ->whereHas('text', fn (Builder $query) => $query->mainFile())
            ->sum('edit_converted_audio_duration');

        $errorTextsPercent = $textsCount > 0
            ? round($errorTextsCount / $textsCount * 100, 2)
            : 0.0;

        $audioProgressPercent = $textsCount > 0
            ? round($audioFinishedTextsCount / $textsCount * 100, 2)
            : 0.0;

        return [
            'speakers_count' => $speakersCount,
            'users_count' => $usersCount,
            'active_editors_count' => $activeEditorsCount,
            'active_speaker_count' => $activeSpeakersCount,
            'active_moderators_count' => $activeModeratorsCount,
            'texts_count' => $textsCount,
            'error_texts_count' => $errorTextsCount,
            'edit_finished_texts_count' => $editFinishedTextsCount,
            'edit_not_finished_texts_count' => $editNotFinishedTextsCount,
            'daily_quota_texts_count' => $dailyQuotaTextsCount,
            'audio_finished_texts_count' => $audioFinishedTextsCount,
            'audio_not_finished_texts_count' => $audioNotFinishedTextsCount,
            'daily_quota_audios_count' => $dailyQuotaAudiosCount,
            'moderator_finished_audios_count' => $moderatorFinishedAudiosCount,
            'moderator_not_finished_audios_count' => $moderatorNotFinishedAudiosCount,
            'daily_quota_check_audios_count' => $dailyQuotaCheckAudiosCount,
            'edit_finished_today_count' => $editFinishedTodayCount,
            'speak_finished_today_count' => $speakFinishedTodayCount,
            'moderator_finished_today_count' => $moderatorFinishedTodayCount,
            'total_audios_count' => $totalAudiosCount,
            'total_audio_duration_seconds' => $totalAudioDurationSeconds,
            'total_audio_duration_hours' => round($totalAudioDurationSeconds / 3600, 2),
            'error_texts_percent' => $errorTextsPercent,
            'audio_progress_percent' => $audioProgressPercent,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
