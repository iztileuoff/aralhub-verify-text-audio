<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\TextResource;
use App\Models\Text;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

#[Group(name: 'Verify - Speaking', weight: 100)]
class SpeakTextController extends Controller
{
    private const MAX_AUDIO_COUNT = 10;

    /**
     * Получить следующий текст для озвучивания.
     *
     * @throws \Throwable
     */
    public function __invoke(Request $request)
    {
        return DB::transaction(function () {

            // resume previously held text
            $heldText = Text::query()
                ->where('edit_speaker_id', auth()->id())
                ->whereNotNull('speak_started_at')
                ->first();

            if ($heldText) {
                $heldText->update(['speak_started_at' => now()]);

                return new TextResource($heldText);
            }

            $baseQuery = Text::query()
                ->excludingSplitParts()
                ->where('file_id', config('dataset.main_file_id'))
                ->where('is_main', true)
                ->where('has_text_error', false)
                ->whereNotNull('edit_original_transcript')
                ->whereNull('speak_started_at')
                ->whereDoesntHave('audio', function ($q) {
                    $q->where('edit_speaker_id', auth()->id());
                });

            $id = $this->pickRandomId((clone $baseQuery)->whereNull('audio_count'));

            if (! $id) {
                $lowestAudioCount = (clone $baseQuery)
                    ->whereNotNull('audio_count')
                    ->where('audio_count', '<', self::MAX_AUDIO_COUNT)
                    ->orderBy('audio_count')
                    ->value('audio_count');

                if ($lowestAudioCount !== null) {
                    $id = $this->pickRandomId(
                        (clone $baseQuery)->where('audio_count', $lowestAudioCount)
                    );
                }
            }

            if (! $id) {
                return response()->json([
                    'message' => 'Тексты для аудиозаписи отсутствуют.',
                ], 404);
            }

            $text = Text::where('id', $id)
                ->lockForUpdate()
                ->first();

            $text->update([
                'speak_started_at' => now(),
                'edit_speaker_id' => auth()->id(),
            ]);

            return new TextResource($text);
        });
    }

    /**
     * Pick a random matching id by probing the primary-key index from a random
     * pivot, allowing the database to stop at the first qualifying row instead
     * of materializing and sorting the whole set with ORDER BY RAND().
     */
    private function pickRandomId(Builder $query): ?int
    {
        $minId = (clone $query)->orderBy('id')->value('id');

        if ($minId === null) {
            return null;
        }

        $maxId = (clone $query)->orderByDesc('id')->value('id');
        $pivot = random_int((int) $minId, (int) $maxId);

        $id = (clone $query)->where('id', '>=', $pivot)->orderBy('id')->value('id')
            ?? (clone $query)->where('id', '<', $pivot)->orderByDesc('id')->value('id');

        return $id === null ? null : (int) $id;
    }
}
