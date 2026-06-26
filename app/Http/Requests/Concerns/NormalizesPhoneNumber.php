<?php

namespace App\Http\Requests\Concerns;

use App\Rules\UzPhoneRule;

trait NormalizesPhoneNumber
{
    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        if (is_string($phone)) {
            $this->merge(['phone' => UzPhoneRule::normalize($phone)]);
        }
    }
}
