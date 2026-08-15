<?php

namespace App\Console\Commands;

use App\Models\Audio;
use App\Models\Text;
use Illuminate\Console\Command;

class NormalizeTextsCommand extends Command
{
    protected $signature = 'texts:normalize';

    protected $description = 'Fill the normalized and tokenized transcripts of texts that do not have them yet';

    public function handle(): void
    {
        //        $totalSamplesCount = Audio::query()
        //            ->where('is_correct', true)
        //            ->whereDate('moderator_finished_at', '>', '2026-03-26 13:00:00')
        //            ->sum('edit_converted_audio_duration');
        //
        //        $count = Audio::query()
        //            ->where('is_correct', true)
        //            ->whereDate('moderator_finished_at', '>', '2026-03-26 13:00:00')
        //            ->count();
        //
        //        $durationInSeconds = (int) ($totalSamplesCount / 16000);
        //
        //        $hours = floor($durationInSeconds / 3600);
        //        $minutes = floor(($durationInSeconds % 3600) / 60);
        //        $seconds = $durationInSeconds % 60;
        //
        //        $formatted = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        //
        //        $this->info("Count: $count");
        //        $this->info("Total samples: {$totalSamplesCount}");
        //        $this->info("Duration: {$formatted}");

        $texts = Text::query()
            ->whereNull('edit_normalized_transcript')
            ->get();

        $count = 0;

        foreach ($texts as $text) {
            // 2. Normalized Transcript
            // Приводим к нижнему регистру и удаляем финальную точку
            $editNormalizedTranscript = mb_strtolower(trim($text->edit_original_transcript, '.?!'));

            // 3. Tokenized Transcript
            // Разбиваем на слова, затем каждое слово на буквы через пробел, разделяя слова пайпом |
            $words = explode(' ', $editNormalizedTranscript);
            $tokenizedParts = array_map(function ($word) {
                // mb_str_split корректно разбивает казахские символы (қ, ө, ұ и т.д.)
                $chars = mb_str_split($word);

                return implode(' ', $chars).' |';
            }, $words);

            $editTokenizedTranscript = implode(' ', $tokenizedParts);

            $text->update([
                'edit_normalized_transcript' => $editNormalizedTranscript,
                'edit_tokenized_transcript' => $editTokenizedTranscript,
            ]);

            $count++;
        }

        $this->info('Normalized texts: '.$count);
    }
}
