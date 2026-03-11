<?php

namespace App\Jobs;

use App\Models\Text;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Storage;

class ProcessAudioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

     // Включаем типизацию для надежности
    public function __construct(
        protected int $textId,
        protected string $path
    ) {}

    public function handle(): void
    {
        $text = Text::find($this->textId);

        if (!$text) {
            return;
        }

        $inputPath = Storage::disk('public')->path($this->path);

        $convertedFileName = 'audio_conv/' . pathinfo($this->path, PATHINFO_FILENAME) . '.wav';
        $outputPath = Storage::disk('public')->path($convertedFileName);

        $process = new Process([
            'ffmpeg',
            '-i', $inputPath,
            '-ar', '16000',
            '-ac', '1',
            '-acodec', 'pcm_s16le',
            '-af', 'highpass=f=80,lowpass=f=8000,silenceremove=start_periods=1:start_duration=0.1:start_threshold=-60dB:stop_periods=1:stop_duration=0.2:stop_threshold=-60dB,loudnorm=I=-16:TP=-1.5:LRA=11,adelay=500|500,apad=pad_dur=0.5',
            '-y',
            $outputPath,
        ]);

        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {

            Log::error('FFmpeg failed', [
                'error' => $process->getErrorOutput()
            ]);

            return;
        }

        $text->edit_converted_audio_filename = $convertedFileName;

        // duration in samples
        $durProcess = new Process([
            'ffprobe',
            '-v', 'error',
            '-select_streams', 'a:0',
            '-show_entries', 'stream=duration_ts',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $outputPath,
        ]);

        $durProcess->run();

        if ($durProcess->isSuccessful()) {
            $text->edit_converted_audio_duration = (int) trim($durProcess->getOutput());
        }

        $text->save();
    }
}
