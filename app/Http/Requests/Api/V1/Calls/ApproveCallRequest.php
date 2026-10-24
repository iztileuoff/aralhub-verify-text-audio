<?php

namespace App\Http\Requests\Api\V1\Calls;

use App\Rules\KarakalpakLatinRule;
use Illuminate\Foundation\Http\FormRequest;

class ApproveCallRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('transcript'))) {
            $this->merge(['transcript' => KarakalpakLatinRule::normalize($this->input('transcript'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'transcript' => ['nullable', 'string', 'min:2', 'max:20000', new KarakalpakLatinRule],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
