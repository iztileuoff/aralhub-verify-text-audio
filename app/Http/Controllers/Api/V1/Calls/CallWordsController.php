<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Enums\CallStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\CallRecording;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

#[Group(name: 'Calls - Trimming', weight: 60)]
class CallWordsController extends Controller
{
    private const CACHE_KEY = 'calls_words_dictionary';

    private const CACHE_FRESH_SECONDS = 3600;

    private const CACHE_STALE_SECONDS = 7200;

    /**
     * Словарь частых слов для подсказок при наборе текста звонка.
     *
     * Слова из текстов сданных и принятых звонков, в нижнем регистре, `í` приведено к `ı`;
     * отсортированы по частоте. Редкие слова (опечатки), голые числа и одиночные буквы не
     * входят. Пересчитывается не чаще раза в час — фронт загружает словарь один раз за сессию.
     */
    public function __invoke(): JsonResponse
    {
        $words = Cache::flexible(
            self::CACHE_KEY,
            [self::CACHE_FRESH_SECONDS, self::CACHE_STALE_SECONDS],
            fn (): array => $this->buildDictionary(),
        );

        return response()->json(['data' => $words], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<int, array{word: string, count: int}>
     */
    private function buildDictionary(): array
    {
        $counts = [];

        $calls = CallRecording::query()
            ->whereIn('status', [CallStatusEnum::SUBMITTED, CallStatusEnum::APPROVED])
            ->whereNotNull('transcript')
            ->select(['id', 'transcript'])
            ->lazyById(500);

        foreach ($calls as $call) {
            $text = strtr(mb_strtolower($call->transcript), ['í' => 'ı']);

            foreach (preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $word) {
                if (mb_strlen($word) < 2 || ! preg_match('/\p{L}/u', $word)) {
                    continue;
                }

                $counts[$word] = ($counts[$word] ?? 0) + 1;
            }
        }

        $minCount = (int) config('calls.words_min_count');
        $counts = array_filter($counts, fn (int $count): bool => $count >= $minCount);

        uksort($counts, fn (string $a, string $b): int => ($counts[$b] <=> $counts[$a]) ?: strcmp($a, $b));

        $dictionary = [];

        foreach (array_slice($counts, 0, (int) config('calls.words_limit'), true) as $word => $count) {
            $dictionary[] = ['word' => (string) $word, 'count' => $count];
        }

        return $dictionary;
    }
}
