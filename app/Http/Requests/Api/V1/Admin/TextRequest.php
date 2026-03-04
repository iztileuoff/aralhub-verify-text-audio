<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TextRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file_id' => ['required'],
            'transcript_id' => ['required'],
            'audio_filename' => ['required'],
            'original_transcript' => ['required'],
            'normalized_transcript' => ['required'],
            'tokenized_transcript' => ['required'],
            'duration' => ['required'],
            'speaker_gender' => ['required'],
            'filter_transcript' => ['required'],
            'processed_audio_filename' => ['required'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
