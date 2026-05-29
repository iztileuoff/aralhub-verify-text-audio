<?php

namespace App\Models;

use App\Enums\GenderEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

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
        'moderator_id',
        'is_correct',
        'moderator_started_at',
        'moderator_finished_at',
        'audio_count',
        'audio_male_count',
        'audio_female_count',
        'is_main',
        'has_text_error',
        'text_error_reported_by',
        'text_error_reported_at',
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
            'moderator_id' => 'integer',
            'is_correct' => 'boolean',
            'moderator_started_at' => 'datetime',
            'moderator_finished_at' => 'datetime',
            'audio_count' => 'integer',
            'audio_male_count' => 'integer',
            'audio_female_count' => 'integer',
            'is_main' => 'boolean',
            'has_text_error' => 'boolean',
            'text_error_reported_by' => 'integer',
            'text_error_reported_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the URL for the edited audio file.
     */
    public function editAudioUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->edit_audio_filename
                ? asset(Storage::url($this->edit_audio_filename))
                : null,
        );
    }

    /**
     * Get the URL for the edited audio file.
     */
    public function editConvertedAudioUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->edit_converted_audio_filename
                ? asset(Storage::url($this->edit_converted_audio_filename))
                : null,
        );
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function ($q) use ($search) {
            $q->where('id', $search);
        });
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

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    public function textErrorReporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'text_error_reported_by');
    }

    public function audio(): HasMany
    {
        return $this->hasMany(Audio::class);
    }
}
