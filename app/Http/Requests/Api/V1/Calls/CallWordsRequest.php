<?php

namespace App\Http\Requests\Api\V1\Calls;

use Illuminate\Foundation\Http\FormRequest;

class CallWordsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prefix' => ['nullable', 'string', 'min:2', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
