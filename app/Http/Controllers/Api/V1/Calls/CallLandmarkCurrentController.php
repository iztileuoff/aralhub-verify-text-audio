<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\CallResource;
use App\Models\CallRecording;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Calls - Landmarks', weight: 62)]
class CallLandmarkCurrentController extends Controller
{
    /** How many times to retry when another admin grabs the same call first. */
    private const TAKE_ATTEMPTS = 10;

    /**
     * Звонок для разметки ориентира: тот, что админ уже держит, иначе случайный свободный.
     *
     * Очередь независима от обрезки: все звонки, кроме помеченных обрезчиком
     * непригодными. Бронь 30 минут; повторный запрос бронь не продлевает.
     * `{"data": null}` — всё прослушано.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $call = $this->resumeOwn($userId) ?? $this->takeRandom($userId);

        return response()->json([
            'data' => $call ? new CallResource($call->load(['trimmer', 'moderator', 'landmark'])) : null,
        ]);
    }

    /**
     * The call this admin already holds — a reload must not hand out another.
     * An expired hold is renewed if nobody else has taken the call meanwhile.
     */
    private function resumeOwn(int $userId): ?CallRecording
    {
        $own = CallRecording::query()
            ->landmarkPending()
            ->where('landmark_assigned_to', $userId)
            ->orderBy('landmark_assigned_at')
            ->first();

        if (! $own || $own->landmark_assigned_at?->gte(CallRecording::lockExpiredBefore())) {
            return $own;
        }

        $renewed = CallRecording::query()
            ->whereKey($own->id)
            ->landmarkPending()
            ->where('landmark_assigned_to', $userId)
            ->update(['landmark_assigned_at' => now()]);

        return $renewed === 1 ? $own->refresh() : null;
    }

    /**
     * Book a random free call with a conditional update, so two admins asking
     * at once never get the same one.
     */
    private function takeRandom(int $userId): ?CallRecording
    {
        for ($attempt = 0; $attempt < self::TAKE_ATTEMPTS; $attempt++) {
            $id = CallRecording::query()->landmarkAvailable()->inRandomOrder()->value('id');

            if ($id === null) {
                return null;
            }

            $taken = CallRecording::query()
                ->whereKey($id)
                ->landmarkAvailable()
                ->update(['landmark_assigned_to' => $userId, 'landmark_assigned_at' => now()]);

            if ($taken === 1) {
                return CallRecording::query()->find($id);
            }
        }

        return null;
    }
}
