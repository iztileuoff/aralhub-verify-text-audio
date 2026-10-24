<?php

namespace App\Http\Requests\Api\V1\Calls;

use Illuminate\Foundation\Http\FormRequest;

class RejectCallRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Напишите, что нужно исправить.',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
