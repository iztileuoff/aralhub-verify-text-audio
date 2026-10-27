<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Calls\CallLandmarkIndexRequest;
use App\Http\Resources\V1\Admin\CallLandmarkResource;
use App\Models\CallLandmark;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group(name: 'Calls - Landmarks', weight: 62)]
class CallLandmarkIndexController extends Controller
{
    /**
     * Словарь ориентиров с их аудио; с `search` — подсказки при наборе
     * (сначала начинающиеся с введённого, потом содержащие его).
     */
    public function __invoke(CallLandmarkIndexRequest $request): AnonymousResourceCollection
    {
        $search = $request->filled('search') ? CallLandmark::normalize($request->validated('search')) : '';
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);

        $landmarks = CallLandmark::query()
            ->with(['primaryCall', 'creator'])
            ->withCount(['examples', 'calls'])
            ->when($search !== '', fn (Builder $query): Builder => $query
                ->whereRaw("normalized_name like ? escape '!'", ['%'.$escaped.'%'])
                ->orderByRaw("case when normalized_name like ? escape '!' then 0 else 1 end", [$escaped.'%']))
            ->orderBy('normalized_name')
            ->paginate($request->validated('per_page') ?? 20);

        return CallLandmarkResource::collection($landmarks);
    }
}
