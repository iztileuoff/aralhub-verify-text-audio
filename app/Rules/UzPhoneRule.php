<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class UzPhoneRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pattern = '/^998([0-9][012345789]|[0-9][125679]|7[01234569])[0-9]{7}$/';

        $normalized = is_string($value) ? self::normalize($value) : '';

        if (! preg_match($pattern, $normalized)) {
            $fail('The :attribute must be a valid Uzbekistan phone number.');
        }
    }

    /**
     * Reduce a user-supplied phone number to the canonical 998XXXXXXXXX form.
     *
     * Accepts common variations such as a leading `+`, spaces, dashes and
     * parentheses, the bare 9-digit subscriber number, and the 0/8 trunk
     * prefix. Input that cannot be normalized is returned unchanged so that
     * validation can still reject it with a meaningful message.
     */
    public static function normalize(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) === 9) {
            return '998'.$digits;
        }

        if (strlen($digits) === 10 && in_array($digits[0], ['0', '8'], true)) {
            return '998'.substr($digits, 1);
        }

        return $digits === '' ? $value : $digits;
    }
}
