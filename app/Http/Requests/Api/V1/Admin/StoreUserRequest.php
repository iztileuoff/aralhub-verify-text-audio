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
            'role' => [Rule::in([RoleEnum::ADMIN->value, RoleEnum::EDITOR->value, RoleEnum::SPEAKER->value, RoleEnum::MODERATOR->value])],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', new UzPhoneRule, Rule::unique('users', 'phone')],
            'password' => ['required', 'string', 'min:8', 'max:64'],
            'gender' => ['required', Rule::in([GenderEnum::MALE->value, GenderEnum::FEMALE->value])],
            'age' => ['required', 'integer', 'min:1', 'max:100'],
            'specialization_id' => ['required', Rule::exists('specializations', 'id')],
            'course' => ['nullable', 'integer'],
            'is_verified' => ['required', 'boolean'],
            'admin_id' => ['nullable', Rule::exists('users', 'id')->where('role', RoleEnum::ADMIN->value)],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
