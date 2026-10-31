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
     *
     * `search`: `#704` или число до 7 цифр — звонок с этим id; любой другой текст (в том числе
     * номер телефона) — часть имени файла, нейтрального или исходного. `status=all` — во всех статусах.
     */
    public function __invoke(CallModerationRequest $request): AnonymousResourceCollection
    {
        $direction = $request->validated('direction') ?? 'asc';
        $status = $request->validated('status') ?? CallStatusEnum::SUBMITTED->value;
        $search = trim((string) $request->validated('search'));

        $calls = CallRecording::query()
            ->with(['trimmer', 'moderator', 'landmark'])
            ->when($status !== 'all', fn (Builder $query): Builder => $query->where('status', $status))
            ->when($search !== '', fn (Builder $query): Builder => $this->search($query, $search))
            ->when(
                $request->filled('trimmer_id'),
                fn (Builder $query): Builder => $query->where('trimmer_id', $request->validated('trimmer_id')),
            )
            ->orderBy('submitted_at', $direction)
            ->orderBy('id', $direction)
            ->paginate($request->validated('per_page') ?? 20);

        return CallResource::collection($calls);
    }

    /**
     * "#704" or a short number is the call id the screen shows; anything else
     * — a phone number included — is looked up in the neutral and the
     * customer's file names.
     */
    private function search(Builder $query, string $search): Builder
    {
        if (preg_match('/^(?:#\s*(\d+)|(\d{1,7}))$/', $search, $match)) {
            return $query->whereKey((int) ($match[1] !== '' ? $match[1] : $match[2]));
        }

        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';

        return $query->where(fn (Builder $inner): Builder => $inner
            ->whereRaw("source_path like ? escape '!'", [$like])
            ->orWhereRaw("original_filename like ? escape '!'", [$like]));
    }
}
