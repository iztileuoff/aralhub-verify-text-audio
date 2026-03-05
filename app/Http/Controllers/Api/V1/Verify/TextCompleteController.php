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

        // 1. Original Transcript (Оригинал как есть)
        $originalTranscript = $validatedText;

        // 2. Normalized Transcript
        // Приводим к нижнему регистру и удаляем финальную точку
        $normalizedTranscript = mb_strtolower(trim($validatedText, '.?!'));

        // 3. Tokenized Transcript
        // Разбиваем на слова, затем каждое слово на буквы через пробел, разделяя слова пайпом |
        $words = explode(' ', $normalizedTranscript);
        $tokenizedParts = array_map(function ($word) {
            // mb_str_split корректно разбивает казахские символы (қ, ө, ұ и т.д.)
            $chars = mb_str_split($word);
            return implode(' ', $chars) . ' |';
        }, $words);

        $tokenizedTranscript = implode(' ', $tokenizedParts);

        $text->edit_original_transcript = $validatedText;
        $text->normalized_transcript = $normalizedTranscript;
        $text->tokenized_transcript = $tokenizedTranscript;
        $text->edit_finished_at = now();
        $text->save();

        return new TextResource($text);
    }
}
