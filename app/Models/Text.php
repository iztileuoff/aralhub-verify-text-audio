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
        'filter_transcript',
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
            'filter_transcript' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
