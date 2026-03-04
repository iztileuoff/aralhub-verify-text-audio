<?php

namespace App\Models;

use App\Enums\GenderEnum;
use Illuminate\Database\Eloquent\Model;

class Text extends Model
{
    protected $fillable = [
        'file_id',
        'transcript_id',
        'audio_filename',
        'original_transcript',
        'normalized_transcript',
        'tokenized_transcript',
        'duration',
        'speaker_gender',
        'filter_original_transcript',
        'filter_normalized_transcript',
        'filter_tokenized_transcript',
        'edit_original_transcript',
        'edit_normalized_transcript',
        'edit_tokenized_transcript',
    ];

    protected function casts(): array
    {
        return [
            'file_id' => 'integer',
            'transcript_id' => 'integer',
            'audio_filename' => 'string',
            'original_transcript' => 'string',
            'normalized_transcript' => 'string',
            'tokenized_transcript' => 'string',
            'duration' => 'integer',
            'speaker_gender' => GenderEnum::class,
            'filter_original_transcript' => 'string',
            'filter_normalized_transcript' => 'string',
            'filter_tokenized_transcript' => 'string',
            'edit_original_transcript' => 'string',
            'edit_normalized_transcript' => 'string',
            'edit_tokenized_transcript' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
