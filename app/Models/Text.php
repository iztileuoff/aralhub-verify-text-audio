<?php

namespace App\Models;

use App\Enums\GenderEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'edit_user_id',
        'edit_started_at',
        'edit_finished_at',
        'edit_cancelled_user_id',
        'edit_audio_filename',
        'edit_converted_audio_filename',
        'edit_converted_audio_duration',
        'speak_started_at',
        'speak_finished_at',
        'edit_speaker_id',
        'edit_speaker_gender',
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
            'edit_user_id' => 'integer',
            'edit_started_at' => 'datetime',
            'edit_finished_at' => 'datetime',
            'edit_cancelled_user_id' => 'integer',
            'edit_audio_filename' => 'string',
            'edit_converted_audio_filename' => 'string',
            'edit_converted_audio_duration' => 'integer',
            'speak_started_at' => 'datetime',
            'speak_finished_at' => 'datetime',
            'edit_speaker_id' => 'integer',
            'edit_speaker_gender' => GenderEnum::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function editUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edit_user_id');
    }

    public function editCancelledUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edit_cancelled_user_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(Action::class);
    }

    public function editSpeaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edit_speaker_id');
    }
}
