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

        if (! preg_match($pattern, $value)) {
            $fail('The :attribute must be a valid Uzbekistan phone number.');
        }
    }
}
