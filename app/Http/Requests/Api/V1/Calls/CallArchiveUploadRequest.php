<?php

namespace App\Http\Requests\Api\V1\Calls;

use App\Models\CallRecording;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One archive (or one volume of it) in a multipart upload: its file name and
 * size, plus — after the upload was started — the upload id and part numbers.
 */
class CallArchiveUploadRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200', 'not_regex:#[/\\\\\x00-\x1F]#', 'regex:/'.CallRecording::UPLOAD_ARCHIVE_PATTERN.'/i'],
            'size' => ['required', 'integer', 'min:1', 'max:'.CallRecording::UPLOAD_MAX_ARCHIVE_BYTES],
            'upload_id' => [$this->routeIs('calls.upload.archive.start') ? 'prohibited' : 'required', 'string', 'max:1024'],
            'parts' => [$this->routeIs('calls.upload.archive.parts') ? 'required' : 'prohibited', 'array', 'min:1', 'max:100'],
            'parts.*' => ['integer', 'min:1', 'max:10000', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Это не архив: нужен .rar, .zip или .7z.',
            'name.not_regex' => 'Недопустимое имя файла.',
            'size.max' => 'Архив больше 50 ГБ.',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
