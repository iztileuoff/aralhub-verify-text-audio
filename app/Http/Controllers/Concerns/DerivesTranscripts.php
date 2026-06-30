<?php

namespace App\Http\Controllers\Concerns;

trait DerivesTranscripts
{
    /**
     * Derive the original/normalized/tokenized transcript triplet from a raw
     * edited text, ready to be assigned to a Text model's edit_* columns.
     *
     * Normalized: lower-cased, stripped of trailing sentence punctuation.
     * Tokenized: each word split into space-separated characters, words
     * delimited by " |" (mb_str_split keeps Kazakh letters like қ, ө, ұ intact).
     *
     * @return array{edit_original_transcript: string, edit_normalized_transcript: string, edit_tokenized_transcript: string}
     */
    protected function deriveTranscripts(string $text): array
    {
        $normalized = mb_strtolower(trim($text, '.?!'));

        $tokenizedParts = array_map(
            fn (string $word): string => implode(' ', mb_str_split($word)).' |',
            explode(' ', $normalized),
        );

        return [
            'edit_original_transcript' => $text,
            'edit_normalized_transcript' => $normalized,
            'edit_tokenized_transcript' => implode(' ', $tokenizedParts),
        ];
    }
}
