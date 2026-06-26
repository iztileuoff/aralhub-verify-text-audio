<?php

namespace App\Models;

use App\Enums\GenderEnum;
use Database\Factories\AudioFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Audio extends Model
{
    /** @use HasFactory<AudioFactory> */
    use HasFactory;

    protected $fillable = [
        'text_id',
        'edit_audio_filename',
        'edit_converted_audio_filename',
        'edit_converted_audio_duration',
        'exported_at',
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
            'exported_at' => 'datetime',
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

    /**
     * Get the URL for the edited audio file.
     */
    public function editAudioUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->edit_audio_filename
                ? Storage::disk('yandex-s3')->url($this->edit_audio_filename)
                : null,
        );
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function ($q) use ($search) {
            $q->where('text_id', $search);
        });
    }

    public function text(): BelongsTo
    {
        return $this->belongsTo(Text::class);
    }

    public function editSpeaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edit_speaker_id');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }
}
