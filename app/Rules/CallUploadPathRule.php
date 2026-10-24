<?php

namespace App\Rules;

use App\Models\CallRecording;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A relative path of an audio file inside the folder the customer picked
 * (e.g. "calls/october/0001.mp3"). It becomes part of a bucket key, so it may
 * not climb out of the batch folder or carry control characters.
 */
class CallUploadPathRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '' || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            $fail('Недопустимое имя файла.');

            return;
        }

        $segments = explode('/', $value);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || mb_strlen($segment) > 200) {
                $fail("Недопустимый путь файла: {$value}.");

                return;
            }
        }

        $extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));

        if (! in_array($extension, CallRecording::UPLOAD_AUDIO_EXTENSIONS, true)) {
            $fail("Это не аудиофайл: {$value}.");
        }
    }

    /**
     * Normalize a path as the browser reports it: Windows separators to "/",
     * no leading or trailing slash.
     */
    public static function normalize(mixed $value): mixed
    {
        return is_string($value) ? trim(str_replace('\\', '/', $value), '/') : $value;
    }
}
