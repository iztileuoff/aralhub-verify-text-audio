<?php

namespace App\Console\Commands;

use App\Models\Action;
use App\Models\Text;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'test';

    protected $description = 'Command description';

    public function handle(): void
    {
        $texts = Text::query()
            ->whereNotNull('edit_user_id')
            ->get();

        foreach ($texts as $text) {
            // 1. Original Transcript (Оригинал как есть)
            $originalTranscript = $text->original_transcript;

            // 2. Normalized Transcript
            // Приводим к нижнему регистру и удаляем финальную точку
            $normalizedTranscript = mb_strtolower(trim($originalTranscript, '.?!'));

            // 3. Tokenized Transcript
            // Разбиваем на слова, затем каждое слово на буквы через пробел, разделяя слова пайпом |
            $words = explode(' ', $normalizedTranscript);
            $tokenizedParts = array_map(function ($word) {
                // mb_str_split корректно разбивает казахские символы (қ, ө, ұ и т.д.)
                $chars = mb_str_split($word);

                return implode(' ', $chars).' |';
            }, $words);

            $tokenizedTranscript = implode(' ', $tokenizedParts);

            $text->normalized_transcript = $normalizedTranscript;
            $text->tokenized_transcript = $tokenizedTranscript;
            $text->save();

            $this->info('ID: ' . $text->id . '. Success!');
        }

        $this->info('Success!');
    }
}
