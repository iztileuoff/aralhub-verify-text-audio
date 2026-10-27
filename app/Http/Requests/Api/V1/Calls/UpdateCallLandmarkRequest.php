<?php

namespace App\Http\Requests\Api\V1\Calls;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCallLandmarkRequest extends FormRequest
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

    public function authorize(): bool
    {
        return true;
    }
}
