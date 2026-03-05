<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\GenderEnum;
use App\Enums\RoleEnum;
use App\Rules\UzPhoneRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in([RoleEnum::ADMIN->value, RoleEnum::VOLUNTEER->value])],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', new UzPhoneRule, Rule::unique('users', 'phone')],
            'password' => ['required', 'string', 'min:8', 'max:64'],
            'gender' => ['required', Rule::in([GenderEnum::MALE->value, GenderEnum::FEMALE->value])],
            'age' => ['required', 'integer', 'min:1', 'max:100'],
            'specialization_id' => ['required', Rule::exists('specializations', 'id')],
            'course' => ['nullable', 'integer'],
            'is_verified' => ['required', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
