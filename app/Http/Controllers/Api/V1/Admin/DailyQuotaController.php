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
        $editFinishedTextsCount = Text::query()
            ->whereNotNull('edit_finished_at')
            ->count();

        $usersCount = User::query()
            ->whereIn('role', [RoleEnum::EDITOR->value, RoleEnum::SPEAKER->value, RoleEnum::MODERATOR->value])
            ->count();

        $dailyQuotaTextsCount = Cache::rememberForever(self::CACHE_KEY, function () {
            return Text::query()
                ->whereNull('edit_finished_at')
                ->orWhere('edit_finished_at', '>=', today())
                ->count();
        });

        return [
            'daily_quota_texts_count' => $dailyQuotaTextsCount,
            'edit_finished_texts_count' => $editFinishedTextsCount,
            'users_count' => $usersCount,
            'quota_per_user' => $usersCount ? intdiv($dailyQuotaTextsCount, $usersCount) : 0,
        ];
    }
}
