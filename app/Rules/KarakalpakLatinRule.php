<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Normalizer;

/**
 * A transcript in the Karakalpak Latin alphabet: A–Z plus Á Ǵ Í Ń Ó Ú (the
 * lowercase pair of Í is the dotless ı), digits and plain punctuation.
 * Cyrillic is refused. Numbers may be written either way — in digits or in
 * words (decided with the customer, 2026-10-24).
 */
class KarakalpakLatinRule implements ValidationRule
{
    /** Anything outside the alphabet, digits and the allowed punctuation. */
    private const FORBIDDEN = '/[^A-Za-zÁáǴǵÍıŃńÓóÚú0-9 .,?!\-\'":;]/u';

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Текст должен быть строкой.');

            return;
        }

        if (preg_match('/\p{Cyrillic}/u', $value)) {
            $fail('Текст пишется латиницей: кириллица не допускается.');

            return;
        }

        if (preg_match_all(self::FORBIDDEN, $value, $matches)) {
            $fail('Недопустимые символы: '.implode(' ', array_unique($matches[0])).'.');

            return;
        }

        if (! preg_match('/\p{L}/u', $value)) {
            $fail('В тексте нет ни одного слова.');
        }
    }

    /**
     * Bring typed text to the canonical form before it is validated and
     * stored: composed Unicode, `í` → `ı` (there is no dotted lowercase í in
     * the alphabet, it is always a typo for ı), typographic quotes to plain
     * ones, and all whitespace — line breaks included — to single spaces, so
     * the transcript fits on one .tsv line.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (class_exists(Normalizer::class)) {
            $value = Normalizer::normalize($value, Normalizer::FORM_C) ?: $value;
        }

        $value = strtr($value, [
            'í' => 'ı',
            '’' => "'",
            '‘' => "'",
            'ʼ' => "'",
            '“' => '"',
            '”' => '"',
            '«' => '"',
            '»' => '"',
        ]);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
