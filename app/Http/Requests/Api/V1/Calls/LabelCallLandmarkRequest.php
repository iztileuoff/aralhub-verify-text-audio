<?php

namespace App\Http\Requests\Api\V1\Calls;

use App\Enums\CallLandmarkDecisionEnum;
use App\Rules\KarakalpakLatinRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabelCallLandmarkRequest extends FormRequest
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
        $needsLandmark = [CallLandmarkDecisionEnum::ALSO->value, CallLandmarkDecisionEnum::DUPLICATE->value];

        return [
            'decision' => ['required', Rule::enum(CallLandmarkDecisionEnum::class)],
            'name' => [
                'required_if:decision,'.CallLandmarkDecisionEnum::PRIMARY->value,
                'prohibited_unless:decision,'.CallLandmarkDecisionEnum::PRIMARY->value,
                'string', 'max:100', new KarakalpakLatinRule,
            ],
            'landmark_id' => [
                'required_if:decision,'.implode(',', $needsLandmark),
                'prohibited_unless:decision,'.implode(',', $needsLandmark),
                'integer', Rule::exists('call_landmarks', 'id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required_if' => 'Напишите ориентир.',
            'landmark_id.required_if' => 'Выберите ориентир из подсказок.',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
