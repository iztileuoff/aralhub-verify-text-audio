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
    private const DICTIONARY_KEY = 'calls_words_dictionary_v2';

    private const NGRAMS_KEY = 'calls_words_ngrams_v1';

    private const CACHE_FRESH_SECONDS = 3600;

    private const CACHE_STALE_SECONDS = 7200;

    private const DEFAULT_SUGGESTIONS = 6;

    /** How many continuations are kept per previous word (or pair of words). */
    private const CONTINUATIONS_KEPT = 20;

    /** The Karakalpak letters folded to plain Latin, so `og` finds `oǵan`. */
    private const FOLD = ['á' => 'a', 'ǵ' => 'g', 'ı' => 'i', 'í' => 'i', 'ń' => 'n', 'ó' => 'o', 'ú' => 'u'];

    /**
     * Подсказки слов при наборе текста звонка.
     *
     * - `prefix` — частые слова, которые начинаются с него (без учёта регистра и букв
     *   `á ǵ ı ń ó ú`: `og` находит `oǵan`).
     * - `prev` (1–2 слова перед курсором) — слова, которые в текстах чаще всего идут сразу
     *   после них: сначала после обоих слов, затем после последнего. Ничего не нашлось —
     *   пустой список, а не просто частые слова. С `prefix` — только продолжения, которые с
     *   него начинаются (хватает одной буквы), затем добор частыми словами по `prefix`.
     * - Без параметров — весь словарь (до 5 000 слов).
     *
     * Не больше `limit` (по умолчанию 6, максимум 20). Слова из текстов сданных и принятых
     * звонков, в нижнем регистре, `í` → `ı`; слова и сочетания реже 3 раз не входят; пары не
     * переходят через `.` `?` `!`. Всё пересчитывается не чаще раза в час. В подсказках по
     * `prev` `count` — сколько раз слово шло после них.
     */
    public function __invoke(CallWordsRequest $request): JsonResponse
    {
        $prev = $request->validated('prev');
        $prefix = $request->validated('prefix');
        $limit = (int) ($request->validated('limit') ?? self::DEFAULT_SUGGESTIONS);

        $words = match (true) {
            filled($prev) => $this->nextWords(self::tokens($prev), $prefix, $limit),
            $prefix !== null => $this->suggest($this->dictionary(), $prefix, $limit),
            default => array_slice($this->dictionary(), 0, (int) config('calls.words_limit')),
        };

        return response()->json([
            'data' => array_map(fn (array $entry): array => ['word' => $entry['word'], 'count' => $entry['count']], $words),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Continuations of the previous words: after both of them first, then after
     * the last one, then — when the next word is started — frequent words by prefix.
     *
     * @param  array<int, string>  $prev
     * @return array<int, array{word: string, count: int}>
     */
    private function nextWords(array $prev, ?string $prefix, int $limit): array
    {
        $ngrams = Cache::flexible(
            self::NGRAMS_KEY,
            [self::CACHE_FRESH_SECONDS, self::CACHE_STALE_SECONDS],
            fn (): array => $this->buildNgrams(),
        );

        $last = array_pop($prev);
        $before = array_pop($prev);
        $candidates = [];

        if ($before !== null && self::isWord($before) && self::isWord($last)) {
            array_push($candidates, ...($ngrams['trigrams'][self::fold($before).' '.self::fold($last)] ?? []));
        }

        if ($last !== null && self::isWord($last)) {
            array_push($candidates, ...($ngrams['bigrams'][self::fold($last)] ?? []));
        }

        $typed = $prefix === null ? null : self::lower($prefix);
        $found = [];

        foreach ($candidates as [$word, $count]) {
            if (count($found) === $limit) {
                break;
            }

            if (isset($found[$word]) || ($typed !== null && ! self::completes($word, $typed))) {
                continue;
            }

            $found[$word] = ['word' => $word, 'count' => $count];
        }

        if ($prefix !== null && count($found) < $limit) {
            foreach ($this->suggest($this->dictionary(), $prefix, $limit, array_keys($found)) as $entry) {
                if (count($found) === $limit) {
                    break;
                }

                $found[$entry['word']] = $entry;
            }
        }

        return array_values($found);
    }

    /**
     * Most frequent words that complete the typed prefix.
     *
     * @param  array<int, array{word: string, count: int, folded: string}>  $dictionary
     * @param  array<int, string>  $exclude
     * @return array<int, array{word: string, count: int, folded: string}>
     */
    private function suggest(array $dictionary, string $prefix, int $limit, array $exclude = []): array
    {
        $typed = self::lower($prefix);
        $excluded = array_flip($exclude);
        $found = [];

        foreach ($dictionary as $entry) {
            if (! isset($excluded[$entry['word']]) && self::completes($entry['word'], $typed)) {
                $found[] = $entry;

                if (count($found) === $limit) {
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * @return array<int, array{word: string, count: int, folded: string}>
     */
    private function dictionary(): array
    {
        return Cache::flexible(
            self::DICTIONARY_KEY,
            [self::CACHE_FRESH_SECONDS, self::CACHE_STALE_SECONDS],
            fn (): array => $this->buildDictionary(),
        );
    }

    /**
     * Every word seen at least `calls.words_min_count` times, most frequent first.
     *
     * @return array<int, array{word: string, count: int, folded: string}>
     */
    private function buildDictionary(): array
    {
        $counts = [];

        foreach ($this->transcripts() as $transcript) {
            foreach (self::tokens($transcript) as $word) {
                if (self::isWord($word)) {
                    $counts[$word] = ($counts[$word] ?? 0) + 1;
                }
            }
        }

        $dictionary = [];

        foreach (self::frequent($counts, PHP_INT_MAX) as [$word, $count]) {
            $dictionary[] = ['word' => $word, 'count' => $count, 'folded' => self::fold($word)];
        }

        return $dictionary;
    }

    /**
     * Which words follow a word (bigrams) and a pair of words (trigrams) within
     * one sentence, keyed by the folded previous word(s). A number or a single
     * letter breaks the chain, a comma does not.
     *
     * @return array{bigrams: array<string, array<int, array{0: string, 1: int}>>, trigrams: array<string, array<int, array{0: string, 1: int}>>}
     */
    private function buildNgrams(): array
    {
        $bigrams = [];
        $trigrams = [];

        foreach ($this->transcripts() as $transcript) {
            foreach (preg_split('/[.?!]+/u', $transcript, -1, PREG_SPLIT_NO_EMPTY) as $sentence) {
                $tokens = self::tokens($sentence);

                foreach ($tokens as $i => $word) {
                    $next = $tokens[$i + 1] ?? null;

                    if ($next === null || ! self::isWord($word) || ! self::isWord($next)) {
                        continue;
                    }

                    $key = self::fold($word);
                    $bigrams[$key][$next] = ($bigrams[$key][$next] ?? 0) + 1;

                    $third = $tokens[$i + 2] ?? null;

                    if ($third !== null && self::isWord($third)) {
                        $pair = $key.' '.self::fold($next);
                        $trigrams[$pair][$third] = ($trigrams[$pair][$third] ?? 0) + 1;
                    }
                }
            }
        }

        $keep = fn (array $table): array => array_filter(array_map(
            fn (array $counts): array => self::frequent($counts, self::CONTINUATIONS_KEPT),
            $table,
        ));

        return ['bigrams' => $keep($bigrams), 'trigrams' => $keep($trigrams)];
    }

    /**
     * @return iterable<int, string>
     */
    private function transcripts(): iterable
    {
        $calls = CallRecording::query()
            ->whereIn('status', [CallStatusEnum::SUBMITTED, CallStatusEnum::APPROVED])
            ->whereNotNull('transcript')
            ->select(['id', 'transcript'])
            ->lazyById(500);

        foreach ($calls as $call) {
            yield $call->transcript;
        }
    }

    /**
     * The entries seen at least `calls.words_min_count` times, most frequent
     * first (ties alphabetically), at most `$keep` of them.
     *
     * @param  array<string|int, int>  $counts
     * @return array<int, array{0: string, 1: int}>
     */
    private static function frequent(array $counts, int $keep): array
    {
        $minCount = (int) config('calls.words_min_count');
        $counts = array_filter($counts, fn (int $count): bool => $count >= $minCount);

        uksort($counts, fn (string|int $a, string|int $b): int => ($counts[$b] <=> $counts[$a]) ?: strcmp((string) $a, (string) $b));

        $list = [];

        foreach (array_slice($counts, 0, $keep, true) as $word => $count) {
            $list[] = [(string) $word, $count];
        }

        return $list;
    }

    /**
     * The word starts with what is typed, ignoring the special letters — and is
     * not just the typed word itself, there would be nothing to complete.
     */
    private static function completes(string $word, string $typed): bool
    {
        return $word !== $typed && str_starts_with(self::fold($word), self::fold($typed));
    }

    /**
     * Lower-cased tokens: split on everything that is not a letter or a digit.
     *
     * @return array<int, string>
     */
    private static function tokens(string $text): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', self::lower($text), -1, PREG_SPLIT_NO_EMPTY);
    }

    /** A dictionary word has a letter and at least two characters. */
    private static function isWord(string $token): bool
    {
        return mb_strlen($token) >= 2 && preg_match('/\p{L}/u', $token) === 1;
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
