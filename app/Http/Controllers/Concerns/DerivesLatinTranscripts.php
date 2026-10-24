<?php

namespace App\Http\Controllers\Concerns;

trait DerivesLatinTranscripts
{
    /**
     * Derive the transcript triplet of a phone call from Karakalpak Latin
     * text, in the same shape DerivesTranscripts gives dataset texts so both
     * end up in one .tsv format.
     *
     * The only difference is lower-casing: the pair of `Í` in this alphabet
     * is the dotless `ı`, while mb_strtolower('Í') would give `í`.
     *
     * @return array{transcript: string, normalized_transcript: string, tokenized_transcript: string}
     */
    protected function deriveLatinTranscripts(string $text): array
    {
        $normalized = mb_strtolower(str_replace('Í', 'ı', trim($text, '.?! ')));

        $tokenizedParts = array_map(
            fn (string $word): string => implode(' ', mb_str_split($word)).' |',
            explode(' ', $normalized),
        );

        return [
            'transcript' => $text,
            'normalized_transcript' => $normalized,
            'tokenized_transcript' => implode(' ', $tokenizedParts),
        ];
    }
}
