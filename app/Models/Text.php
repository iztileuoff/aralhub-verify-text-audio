<?php

namespace App\Models;

use App\Enums\GenderEnum;
use Carbon\Carbon;
use Database\Factories\TextFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Text extends Model
{
    /** @use HasFactory<TextFactory> */
    use HasFactory;

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
        'is_split_part',
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
            'is_split_part' => 'boolean',
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
                ? Storage::disk('yandex-s3')->url($this->edit_audio_filename)
                : null,
        );
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function ($q) use ($search) {
            $q->where('id', $search);
        });
    }

    /**
     * Constrain a datetime column to a single calendar day using an
     * index-friendly range instead of wrapping the column in DATE().
     */
    public function scopeOnDate(Builder $query, string $column, Carbon|string $date): Builder
    {
        $day = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $query->whereBetween($column, [
            $day->copy()->startOfDay(),
            $day->copy()->endOfDay(),
        ]);
    }

    /**
     * Limit the query to the main texts of the primary dataset file.
     */
    public function scopeMainFile(Builder $query): Builder
    {
        return $query->where('is_main', true)
            ->where('file_id', config('dataset.main_file_id'));
    }

    /**
     * Exclude the child texts created for split audio halves; they are not
     * dataset texts and must stay out of work queues, lists and counters.
     */
    public function scopeExcludingSplitParts(Builder $query): Builder
    {
        return $query->where('is_split_part', false);
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
