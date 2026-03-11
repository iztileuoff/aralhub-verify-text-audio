<?php

namespace App\Http\Requests\Api\V1\Verify;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class StoreSpeakTextRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'audio' => ['required', 'file', 'mimes:mp3,wav,m4a,ogg,aac'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->hasFile('audio')) {
            $file = $this->file('audio');

            Log::info('Audio before validation', [
                'original_name' => $file->getClientOriginalName(),
                'client_mime' => $file->getClientMimeType(),
                'detected_mime' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension(),
                'size' => $file->getSize(),
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }
}
