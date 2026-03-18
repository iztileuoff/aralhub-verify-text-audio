<?php

namespace App\Console\Commands;

use App\Enums\GenderEnum;
use App\Models\Action;
use App\Models\Audio;
use App\Models\Text;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'test';

    protected $description = 'Command description';

    public function handle(): void
    {
        $texts = Text::query()
            ->whereDate('speak_finished_at', '<', '2026-03-18')
            ->get();

//        foreach ($texts as $text) {
//            // 1. Original Transcript (Оригинал как есть)
//            $originalTranscript = $text->original_transcript;
//
//            // 2. Normalized Transcript
//            // Приводим к нижнему регистру и удаляем финальную точку
//            $normalizedTranscript = mb_strtolower(trim($originalTranscript, '.?!'));
//
//            // 3. Tokenized Transcript
//            // Разбиваем на слова, затем каждое слово на буквы через пробел, разделяя слова пайпом |
//            $words = explode(' ', $normalizedTranscript);
//            $tokenizedParts = array_map(function ($word) {
//                // mb_str_split корректно разбивает казахские символы (қ, ө, ұ и т.д.)
//                $chars = mb_str_split($word);
//
//                return implode(' ', $chars).' |';
//            }, $words);
//
//            $tokenizedTranscript = implode(' ', $tokenizedParts);
//
//            $text->normalized_transcript = $normalizedTranscript;
//            $text->tokenized_transcript = $tokenizedTranscript;
//            $text->save();
//
//            $this->info('ID: ' . $text->id . '. Success!');
//        }

        foreach ($texts as $text) {
//            $audio = Audio::create([
//                'text_id' => $text->id,
//                'edit_audio_filename' => $text->edit_audio_filename,
//                'edit_converted_audio_filename' => $text->edit_converted_audio_filename,
//                'edit_converted_audio_duration' => $text->edit_converted_audio_duration,
//                'speak_started_at' => $text->speak_started_at,
//                'speak_finished_at' => $text->speak_finished_at,
//                'edit_speaker_id' => $text->edit_speaker_id,
//                'edit_speaker_gender' => $text->edit_speaker_gender,
//            ]);

            $text->audio_count = $text->audio()->count();
            $text->audio_male_count = $text->audio()->where('edit_speaker_gender', GenderEnum::MALE->value)->count();
            $text->audio_female_count = $text->audio()->where('edit_speaker_gender', GenderEnum::FEMALE->value)->count();
            $text->save();
        }

        $this->info('Success!');
    }
}
