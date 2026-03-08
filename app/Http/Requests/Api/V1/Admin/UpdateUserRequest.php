<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\GenderEnum;
use App\Enums\RoleEnum;
use App\Rules\UzPhoneRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'role' => [Rule::in([RoleEnum::ADMIN->value, RoleEnum::EDITOR->value, RoleEnum::SPEAKER->value, RoleEnum::MODERATOR->value])],
            'first_name' => ['string', 'max:255'],
            'last_name' => ['string', 'max:255'],
            'phone' => [new UzPhoneRule, Rule::unique('users', 'phone')->ignore($this->user()->id)],
            'password' => ['string', 'min:8', 'max:64'],
            'gender' => [Rule::in([GenderEnum::MALE->value, GenderEnum::FEMALE->value])],
            'age' => ['integer', 'min:1', 'max:100'],
            'specialization_id' => [Rule::exists('specializations', 'id')],
            'course' => ['nullable', 'integer'],
            'is_verified' => ['boolean'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
