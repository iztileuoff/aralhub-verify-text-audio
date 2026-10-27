<?php

namespace App\Http\Requests\Api\V1\Calls;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DuplicateCallLandmarkRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'landmark_id' => ['required', 'integer', Rule::exists('call_landmarks', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'landmark_id.required' => 'Выберите ориентир из подсказок.',
            'landmark_id.exists' => 'Такого ориентира нет.',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
