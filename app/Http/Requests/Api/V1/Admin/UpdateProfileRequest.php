<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Rules\UzPhoneRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['string', 'max:255'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
