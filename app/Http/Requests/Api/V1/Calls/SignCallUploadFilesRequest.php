<?php

namespace App\Http\Requests\Api\V1\Calls;

use App\Models\CallRecording;
use App\Rules\CallUploadPathRule;
use Illuminate\Foundation\Http\FormRequest;

class SignCallUploadFilesRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $files = $this->input('files');

        if (is_array($files)) {
            $this->merge(['files' => array_map(
                fn (mixed $file): mixed => is_array($file) ? [...$file, 'path' => CallUploadPathRule::normalize($file['path'] ?? null)] : $file,
                $files,
            )]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:200'],
            'files.*.path' => ['required', 'string', 'max:500', new CallUploadPathRule],
            'files.*.size' => ['required', 'integer', 'min:1', 'max:'.CallRecording::UPLOAD_MAX_FILE_BYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.*.size.max' => 'Файл больше '.intdiv(CallRecording::UPLOAD_MAX_FILE_BYTES, 1024 * 1024).' МБ — это не похоже на звонок.',
            'files.*.size.min' => 'Пустой файл.',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
