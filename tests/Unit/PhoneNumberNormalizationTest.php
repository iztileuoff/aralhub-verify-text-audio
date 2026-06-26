<?php

use App\Rules\UzPhoneRule;

it('normalizes common phone formats to the canonical form', function (?string $input, string $expected) {
    expect(UzPhoneRule::normalize($input))->toBe($expected);
})->with([
    'already canonical' => ['998901234567', '998901234567'],
    'leading plus' => ['+998901234567', '998901234567'],
    'spaces and dashes' => ['+998 90 123-45-67', '998901234567'],
    'parentheses' => ['998 (90) 123 45 67', '998901234567'],
    'bare 9-digit subscriber' => ['901234567', '998901234567'],
    'zero trunk prefix' => ['0901234567', '998901234567'],
    'eight trunk prefix' => ['8901234567', '998901234567'],
    'null' => [null, ''],
    'non-numeric stays unchanged' => ['not-a-phone', 'not-a-phone'],
    'wrong length stays digits' => ['12345', '12345'],
]);
