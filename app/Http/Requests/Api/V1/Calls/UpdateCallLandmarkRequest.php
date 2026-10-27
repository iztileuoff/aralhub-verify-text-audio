<?php

namespace App\Http\Requests\Api\V1\Calls;

use App\Rules\KarakalpakLatinRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCallLandmarkRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => KarakalpakLatinRule::normalize($this->input('name'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', new KarakalpakLatinRule],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
