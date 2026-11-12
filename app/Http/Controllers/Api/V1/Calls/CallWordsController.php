<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Enums\CallStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Calls\CallWordsRequest;
use App\Models\CallRecording;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

#[Group(name: 'Calls - Trimming', weight: 60)]
class CallWordsController extends Controller
{
    private const CACHE_KEY = 'calls_words_dictionary_v2';

    private const CACHE_FRESH_SECONDS = 3600;

    private const CACHE_STALE_SECONDS = 7200;

    private const DEFAULT_SUGGESTIONS = 6;

    /** The Karakalpak letters folded to plain Latin, so `og` finds `oǵan`. */
    private const FOLD = ['á' => 'a', 'ǵ' => 'g', 'ı' => 'i', 'í' => 'i', 'ń' => 'n', 'ó' => 'o', 'ú' => 'u'];

    /**
     * Подсказки слов при наборе текста звонка.
     *
     * С `prefix` — частые слова, которые начинаются с него (без учёта регистра и букв
     * `á ǵ ı ń ó ú`: `og` находит `oǵan`), не больше `limit` (по умолчанию 6, максимум 20).
     * Без `prefix` — весь словарь (до 5 000 слов). Слова из текстов сданных и принятых
     * звонков, в нижнем регистре, `í` приведено к `ı`, по частоте; слова реже 3 раз, голые
     * числа и одиночные буквы не входят. Словарь пересчитывается не чаще раза в час.
     */
    public function __invoke(CallWordsRequest $request): JsonResponse
    {
        $dictionary = Cache::flexible(
            self::CACHE_KEY,
            [self::CACHE_FRESH_SECONDS, self::CACHE_STALE_SECONDS],
            fn (): array => $this->buildDictionary(),
        );

        $prefix = $request->validated('prefix');

        $words = $prefix === null
            ? array_slice($dictionary, 0, (int) config('calls.words_limit'))
            : $this->suggest($dictionary, $prefix, (int) ($request->validated('limit') ?? self::DEFAULT_SUGGESTIONS));

        return response()->json([
            'data' => array_map(fn (array $entry): array => ['word' => $entry['word'], 'count' => $entry['count']], $words),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Most frequent words whose folded form starts with the folded prefix. The
     * word typed in full is left out — there is nothing to complete.
     *
     * @param  array<int, array{word: string, count: int, folded: string}>  $dictionary
     * @return array<int, array{word: string, count: int, folded: string}>
     */
    private function suggest(array $dictionary, string $prefix, int $limit): array
    {
        $typed = self::lower($prefix);
        $folded = self::fold($typed);
        $found = [];

        foreach ($dictionary as $entry) {
            if ($entry['word'] !== $typed && str_starts_with($entry['folded'], $folded)) {
                $found[] = $entry;

                if (count($found) === $limit) {
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * Every word seen at least `calls.words_min_count` times, most frequent first.
     *
     * @return array<int, array{word: string, count: int, folded: string}>
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
            foreach (preg_split('/[^\p{L}\p{N}]+/u', self::lower($call->transcript), -1, PREG_SPLIT_NO_EMPTY) as $word) {
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

        foreach ($counts as $word => $count) {
            $dictionary[] = ['word' => (string) $word, 'count' => $count, 'folded' => self::fold((string) $word)];
        }

        return $dictionary;
    }

    /** Lower case with `í` → `ı`, the form words are stored in. */
    private static function lower(string $text): string
    {
        return strtr(mb_strtolower($text), ['í' => 'ı']);
    }

    private static function fold(string $word): string
    {
        return strtr($word, self::FOLD);
    }
}
