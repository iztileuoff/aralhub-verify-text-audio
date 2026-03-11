<?php

namespace App\Http\Requests\Api\V1\Verify;

use Illuminate\Foundation\Http\FormRequest;

class StoreSpeakTextRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'audio' => ['required', 'file', 'mimes:mp3,wav,m4a,ogg,aac'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
