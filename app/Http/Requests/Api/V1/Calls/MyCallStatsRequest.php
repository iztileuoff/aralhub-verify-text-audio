<?php

namespace App\Http\Requests\Api\V1\Calls;

use Illuminate\Foundation\Http\FormRequest;

class MyCallStatsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
