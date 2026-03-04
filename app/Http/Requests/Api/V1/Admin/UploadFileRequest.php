<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                // Allow both .tsv and plain text MIME types
                'mimetypes:text/tab-separated-values,text/plain,application/octet-stream',
                // Max 50 MB — adjust as needed
                'max:51200',
            ],
            'user_id' => ['nullable'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => $this->user()->id,
        ]);
    }

    public function messages(): array
    {
        return [
            'file.required' => 'A TSV file is required.',
            'file.mimetypes' => 'The file must be a valid TSV (tab-separated values) file.',
            'file.max' => 'The file must not exceed 50 MB.',
        ];
    }
}
