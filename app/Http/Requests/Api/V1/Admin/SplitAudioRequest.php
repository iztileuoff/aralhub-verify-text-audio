<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Audio;
use Illuminate\Foundation\Http\FormRequest;

class SplitAudioRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parts' => ['required', 'array', 'size:2'],
            'parts.*.audio' => ['required', 'file', 'mimes:wav'],
            'parts.*.duration' => ['required', 'integer', 'min:1', 'max:'.(Audio::STT_MAX_DURATION_SAMPLES - 1)],
            'parts.*.original_transcript' => ['required', 'string'],
            'parts.*.normalized_transcript' => ['nullable', 'string'],
            'parts.*.tokenized_transcript' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parts.size' => 'Аудио нужно разрезать ровно на 2 части.',
            'parts.*.duration.max' => 'Каждая часть должна быть короче 30 секунд ('.(Audio::STT_MAX_DURATION_SAMPLES - 1).' сэмплов).',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
