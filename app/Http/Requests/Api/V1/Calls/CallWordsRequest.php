<?php

namespace App\Http\Requests\Api\V1\Calls;

use Illuminate\Foundation\Http\FormRequest;

class CallWordsRequest extends FormRequest
{
    /**
     * After the previous words even one typed letter narrows the next word;
     * on its own a prefix needs two.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prev' => ['nullable', 'string', 'max:100'],
            'prefix' => ['nullable', 'string', $this->filled('prev') ? 'min:1' : 'min:2', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
