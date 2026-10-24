<?php

namespace App\Http\Requests\Api\V1\Calls;

use App\Rules\KarakalpakLatinRule;
use App\Rules\Pcm16kMonoWavRule;
use Illuminate\Foundation\Http\FormRequest;

class SubmitCallRequest extends FormRequest
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
            'audio' => [
                'required',
                'file',
                'max:'.config('calls.max_upload_kb'),
                new Pcm16kMonoWavRule(config('calls.max_duration_seconds')),
            ],
            'transcript' => ['required', 'string', 'min:2', 'max:20000', new KarakalpakLatinRule],
            'source_duration_ms' => ['required', 'integer', 'min:1'],
            'trim_start_ms' => ['required', 'integer', 'min:0'],
            'trim_end_ms' => ['required', 'integer', 'gt:trim_start_ms', 'lte:source_duration_ms'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'audio.max' => 'Файл больше '.config('calls.max_upload_kb').' КБ.',
            'trim_end_ms.gt' => 'Конец обрезки должен быть позже начала.',
            'trim_end_ms.lte' => 'Конец обрезки не может быть позже конца исходника.',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
