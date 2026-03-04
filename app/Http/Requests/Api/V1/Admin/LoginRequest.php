<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Rules\UzPhoneRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['required', new UzPhoneRule],
            'password' => ['required', 'string', 'max:254'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
