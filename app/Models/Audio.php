<?php

namespace App\Models;

use App\Enums\GenderEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Audio extends Model
{
    protected $fillable = [
        'text_id',
        'edit_audio_filename',
        'edit_converted_audio_filename',
        'edit_converted_audio_duration',
        'speak_started_at',
        'speak_finished_at',
        'edit_speaker_id',
        'edit_speaker_gender',
        'moderator_id',
        'is_correct',
        'moderator_started_at',
        'moderator_finished_at',
    ];

    protected function casts(): array
    {
        return [
            'text_id' => 'integer',
            'edit_audio_filename' => 'string',
            'edit_converted_audio_filename' => 'string',
            'edit_converted_audio_duration' => 'integer',
            'speak_started_at' => 'datetime',
            'speak_finished_at' => 'datetime',
            'edit_speaker_id' => 'integer',
            'edit_speaker_gender' => GenderEnum::class,
            'moderator_id' => 'integer',
            'is_correct' => 'boolean',
            'moderator_started_at' => 'datetime',
            'moderator_finished_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function text(): BelongsTo
    {
        return $this->belongsTo(Text::class);
    }
}
