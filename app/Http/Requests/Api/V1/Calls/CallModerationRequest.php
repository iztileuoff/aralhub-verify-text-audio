<?php

namespace App\Http\Requests\Api\V1\Calls;

use App\Enums\CallStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CallModerationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in([
                CallStatusEnum::SUBMITTED->value,
                CallStatusEnum::APPROVED->value,
                CallStatusEnum::REJECTED->value,
                CallStatusEnum::SKIPPED->value,
            ])],
            'trimmer_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
