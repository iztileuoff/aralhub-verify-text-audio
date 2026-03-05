<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\TextResource;
use App\Models\Text;
use Illuminate\Http\Request;

class TextCompleteController extends Controller
{
    public function __invoke(Request $request, Text $text)
    {
        $request->validate([
            'text' => ['required', 'string'],
        ]);

        $validatedText = $request->input('text');

        // 1. Original Transcript (как есть)
        $originalTranscript = $validatedText;

        // 2. Normalized Transcript
        // Приводим к нижнему регистру и удаляем пунктуацию (кроме кавычек, как в вашем примере)
        $normalizedTranscript = mb_strtolower($validatedText);
        // Удаляем запятые, точки, скобки
        $normalizedTranscript = preg_replace('/[,().]/u', '', $normalizedTranscript);

        // 3. Tokenized Transcript
        // Разбиваем строку на слова
        $words = explode(' ', $normalizedTranscript);
        $tokenizedArray = [];

        foreach ($words as $word) {
            if (empty($word)) {
                continue;
            }

            // Разбиваем слово на отдельные символы (мультибайтовая поддержка)
            $chars = mb_str_split($word);
            // Соединяем символы через пробел и добавляем в массив
            $tokenizedArray[] = implode(' ', $chars);
        }

        // Соединяем слова через пайп | и добавляем финальный пайп в конце
        $tokenizedTranscript = implode(' | ', $tokenizedArray).' |';

        $text->edit_original_transcript = $validatedText;
        $text->normalized_transcript = $normalizedTranscript;
        $text->tokenized_transcript = $tokenizedTranscript;
        $text->edit_finished_at = now();
        $text->save();

        return new TextResource($text);
    }
}
