<?php

use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Bytes of a silent RIFF/WAVE file. Defaults to the dataset format
 * (16 kHz, mono, 16-bit PCM); $listChunk puts an odd-sized LIST chunk before
 * "data", the way real encoders do, to exercise chunk walking and padding.
 */
function wavBytes(float $seconds, int $sampleRate = 16000, int $channels = 1, int $bits = 16, int $format = 1, bool $listChunk = false): string
{
    $blockAlign = $channels * intdiv($bits, 8);
    $data = str_repeat("\0", (int) round($seconds * $sampleRate) * $blockAlign);
    $fmt = pack('vvVVvv', $format, $channels, $sampleRate, $sampleRate * $blockAlign, $blockAlign, $bits);

    $chunks = 'fmt '.pack('V', strlen($fmt)).$fmt;

    if ($listChunk) {
        $chunks .= 'LIST'.pack('V', 7)."INFOabc\0";
    }

    $chunks .= 'data'.pack('V', strlen($data)).$data;

    return 'RIFF'.pack('V', 4 + strlen($chunks)).'WAVE'.$chunks;
}

/**
 * An uploaded WAV file built by wavBytes().
 */
function fakeWav(float $seconds = 2, int $sampleRate = 16000, int $channels = 1, int $bits = 16, int $format = 1, bool $listChunk = false): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'call.wav',
        wavBytes($seconds, $sampleRate, $channels, $bits, $format, $listChunk),
    );
}
