<?php

namespace App\Http\Requests\Api\V1\Calls;

use App\Enums\CallSkipReasonEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SkipCallRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(CallSkipReasonEnum::class)],
            'comment' => ['nullable', 'required_if:reason,'.CallSkipReasonEnum::OTHER->value, 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'comment.required_if' => 'Опишите причину в комментарии.',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
