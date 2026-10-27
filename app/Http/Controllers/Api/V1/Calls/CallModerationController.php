<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Enums\CallStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Calls\CallModerationRequest;
use App\Http\Resources\V1\Admin\CallResource;
use App\Models\CallRecording;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group(name: 'Calls - Moderation', weight: 61)]
class CallModerationController extends Controller
{
    /**
     * Звонки для модерации; по умолчанию — сданные и ждущие решения.
     */
    public function __invoke(CallModerationRequest $request): AnonymousResourceCollection
    {
        $direction = $request->validated('direction') ?? 'asc';

        $calls = CallRecording::query()
            ->with(['trimmer', 'moderator', 'landmark'])
            ->where('status', $request->validated('status') ?? CallStatusEnum::SUBMITTED->value)
            ->when(
                $request->filled('trimmer_id'),
                fn (Builder $query): Builder => $query->where('trimmer_id', $request->validated('trimmer_id')),
            )
            ->orderBy('submitted_at', $direction)
            ->orderBy('id', $direction)
            ->paginate($request->validated('per_page') ?? 20);

        return CallResource::collection($calls);
    }
}
