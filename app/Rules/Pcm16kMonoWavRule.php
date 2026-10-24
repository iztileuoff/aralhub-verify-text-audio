<?php

namespace App\Rules;

use App\Models\Audio;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A WAV file in the dataset format — 16 kHz, mono, 16-bit PCM — no longer
 * than the given number of seconds. Checked from the file header itself, so
 * no ffmpeg is needed on the server (there is none on prod).
 */
class Pcm16kMonoWavRule implements ValidationRule
{
    private const FORMAT_PCM = 1;

    private const FORMAT_EXTENSIBLE = 0xFFFE;

    public function __construct(private int $maxSeconds) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $wav = self::inspect($value->getRealPath());

        if ($wav === null) {
            $fail('Файл не похож на WAV.');

            return;
        }

        if ($wav['format'] !== self::FORMAT_PCM
            || $wav['channels'] !== 1
            || $wav['sample_rate'] !== Audio::SAMPLE_RATE
            || $wav['bits'] !== 16) {
            $fail(sprintf(
                'Нужен WAV 16 kHz mono 16-bit PCM, получен %d Hz, каналов: %d, %d бит%s.',
                $wav['sample_rate'],
                $wav['channels'],
                $wav['bits'],
                $wav['format'] === self::FORMAT_PCM ? '' : ', не PCM',
            ));

            return;
        }

        $samples = self::samples($wav);

        if ($samples === 0) {
            $fail('В файле нет звука.');

            return;
        }

        if ($samples > $this->maxSeconds * Audio::SAMPLE_RATE) {
            $fail("Аудио длиннее {$this->maxSeconds} секунд — это не тот файл?");
        }
    }

    /**
     * Number of samples in a 16-bit mono file described by inspect().
     *
     * @param  array{format: int, channels: int, sample_rate: int, bits: int, data_bytes: int}  $wav
     */
    public static function samples(array $wav): int
    {
        return intdiv($wav['data_bytes'], 2);
    }

    /**
     * Read the format and the size of the audio data from a RIFF/WAVE header.
     * Walks the chunks instead of assuming the canonical 44-byte layout, since
     * encoders add LIST/fact chunks before "data". A "data" size larger than
     * what is actually on disk (streaming writers) is clamped to the file.
     *
     * @return array{format: int, channels: int, sample_rate: int, bits: int, data_bytes: int}|null
     */
    public static function inspect(string|false $path): ?array
    {
        if ($path === false || ! is_readable($path)) {
            return null;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        try {
            $riff = fread($handle, 12);

            if (strlen($riff) !== 12 || substr($riff, 0, 4) !== 'RIFF' || substr($riff, 8, 4) !== 'WAVE') {
                return null;
            }

            $fileSize = filesize($path);
            $fmt = null;

            while (($header = fread($handle, 8)) !== false && strlen($header) === 8) {
                $id = substr($header, 0, 4);
                $size = unpack('V', substr($header, 4, 4))[1];

                if ($id === 'fmt ') {
                    $body = fread($handle, $size);

                    if ($body === false || strlen($body) < 16) {
                        return null;
                    }

                    $fmt = unpack('vformat/vchannels/Vsample_rate/Vbyte_rate/vblock_align/vbits', $body);

                    if ($fmt['format'] === self::FORMAT_EXTENSIBLE && strlen($body) >= 26) {
                        $fmt['format'] = unpack('v', substr($body, 24, 2))[1];
                    }

                    if ($size % 2 === 1) {
                        fseek($handle, 1, SEEK_CUR);
                    }

                    continue;
                }

                if ($id === 'data') {
                    if ($fmt === null) {
                        return null;
                    }

                    return [
                        'format' => $fmt['format'],
                        'channels' => $fmt['channels'],
                        'sample_rate' => $fmt['sample_rate'],
                        'bits' => $fmt['bits'],
                        'data_bytes' => min($size, max(0, $fileSize - ftell($handle))),
                    ];
                }

                fseek($handle, $size + ($size % 2), SEEK_CUR);
            }

            return null;
        } finally {
            fclose($handle);
        }
    }
}
