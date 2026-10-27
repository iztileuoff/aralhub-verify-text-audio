<?php

namespace App\Http\Requests\Api\V1\Calls;

use Illuminate\Foundation\Http\FormRequest;

class LabelCallLandmarkRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim((string) preg_replace('/\s+/u', ' ', $this->input('name')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Напишите ориентир.',
            'name.min' => 'Ориентир — минимум 2 символа.',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
