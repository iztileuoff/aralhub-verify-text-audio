<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateProfileRequest;
use App\Http\Resources\V1\Admin\ProfileResource;
use App\Models\Text;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DailyQuotaController extends Controller
{
    private const CACHE_KEY = 'daily_quota_texts_count';

    public function show(Request $request)
    {
        return response()->json([
            'data' => [
                'daily_quota_texts_count' => $this->getDailyQuota(),
            ]
        ]);
    }

    public function update(UpdateProfileRequest $request)
    {
        Cache::forget(self::CACHE_KEY);

        return response()->json([
            'data' => [
                'daily_quota_texts_count' => $this->getDailyQuota(),
            ]
        ]);
    }

    private function getDailyQuota()
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return Text::query()
                ->whereNull('edit_finished_at')
                ->orWhereDate('edit_finished_at', today())
                ->count();
        });
    }
}
