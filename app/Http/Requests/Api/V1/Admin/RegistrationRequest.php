<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\GenderEnum;
use App\Enums\RoleEnum;
use App\Rules\UzPhoneRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'role' => ['nullable'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', new UzPhoneRule, Rule::unique('users', 'phone')],
            'password' => ['required', 'string', 'min:8', 'max:64'],
            'gender' => ['required', Rule::in([GenderEnum::MALE->value, GenderEnum::FEMALE->value])],
            'age' => ['required', 'integer', 'min:1', 'max:100'],
            'specialization_id' => ['required', Rule::exists('specializations', 'id')],
            'course' => ['required', 'integer'],
            'is_verified' => ['nullable'],
            'admin_id' => ['nullable', Rule::exists('users', 'id')->where('role', RoleEnum::ADMIN->value)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'role' => RoleEnum::SPEAKER->value,
            'is_verified' => false,
        ]);
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Ism kiritilishi majburiy.',
            'first_name.string' => 'Ism matn bo‘lishi kerak.',
            'first_name.max' => 'Ism 255 ta belgidan oshmasligi kerak.',

            'last_name.required' => 'Familiya kiritilishi majburiy.',
            'last_name.string' => 'Familiya matn bo‘lishi kerak.',
            'last_name.max' => 'Familiya 255 ta belgidan oshmasligi kerak.',

            'phone.required' => 'Telefon raqam kiritilishi majburiy.',
            'phone.unique' => 'Bu telefon raqam allaqachon ro‘yxatdan o‘tgan.',

            'password.required' => 'Parol kiritilishi majburiy.',
            'password.string' => 'Parol matn bo‘lishi kerak.',
            'password.min' => 'Parol kamida 8 ta belgidan iborat bo‘lishi kerak.',
            'password.max' => 'Parol eng ko‘pi bilan 64 ta belgidan iborat bo‘lishi mumkin.',

            'gender.required' => 'Jins tanlanishi majburiy.',
            'gender.in' => 'Jins noto‘g‘ri tanlangan.',

            'age.required' => 'Yosh kiritilishi majburiy.',
            'age.integer' => 'Yosh butun son bo‘lishi kerak.',
            'age.min' => 'Yosh kamida 1 bo‘lishi kerak.',
            'age.max' => 'Yosh 100 dan oshmasligi kerak.',

            'specialization_id.required' => 'Mutaxassislik tanlanishi majburiy.',
            'specialization_id.exists' => 'Tanlangan mutaxassislik mavjud emas.',

            'course.required' => 'Kurs kiritilishi majburiy.',
            'course.integer' => 'Kurs butun son bo‘lishi kerak.',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
