<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Enums\CallStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Calls\MyCallsRequest;
use App\Http\Resources\V1\Admin\CallResource;
use App\Models\CallRecording;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group(name: 'Calls - Trimming', weight: 60)]
class MyCallsController extends Controller
{
    /**
     * Мои сданные работы (только просмотр).
     */
    public function __invoke(MyCallsRequest $request): AnonymousResourceCollection
    {
        $calls = CallRecording::query()
            ->with(['trimmer', 'moderator'])
            ->where('trimmer_id', $request->user()->id)
            ->when(
                $request->filled('status'),
                fn (Builder $query): Builder => $query->where('status', $request->validated('status')),
                fn (Builder $query): Builder => $query->whereNot('status', CallStatusEnum::IN_PROGRESS),
            )
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($request->validated('per_page') ?? 20);

        return CallResource::collection($calls);
    }
}
