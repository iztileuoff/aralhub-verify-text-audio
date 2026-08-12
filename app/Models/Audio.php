<?php

namespace App\Models;

use App\Enums\AudioSplitStatusEnum;
use App\Enums\GenderEnum;
use Carbon\Carbon;
use Database\Factories\AudioFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Audio extends Model
{
    /** @use HasFactory<AudioFactory> */
    use HasFactory;

    /** Частота дискретизации всех конвертированных аудио. */
    public const SAMPLE_RATE = 16000;

    /** Максимальная длительность одного аудио для STT, в секундах. */
    public const STT_MAX_DURATION_SECONDS = 30;

    /** Порог длительности STT в сэмплах (edit_converted_audio_duration хранится в сэмплах). */
    public const STT_MAX_DURATION_SAMPLES = self::SAMPLE_RATE * self::STT_MAX_DURATION_SECONDS;

    protected $fillable = [
        'text_id',
        'parent_audio_id',
        'edit_audio_filename',
        'storage_disk',
        'edit_converted_audio_filename',
        'edit_converted_audio_duration',
        'exported_at',
        'export_id',
        'speak_started_at',
        'speak_finished_at',
        'edit_speaker_id',
        'edit_speaker_gender',
        'moderator_id',
        'is_correct',
        'split_status',
        'moderator_started_at',
        'moderator_finished_at',
    ];

    protected function casts(): array
    {
        return [
            'text_id' => 'integer',
            'parent_audio_id' => 'integer',
            'edit_audio_filename' => 'string',
            'storage_disk' => 'string',
            'edit_converted_audio_filename' => 'string',
            'edit_converted_audio_duration' => 'integer',
            'exported_at' => 'datetime',
            'export_id' => 'integer',
            'speak_started_at' => 'datetime',
            'speak_finished_at' => 'datetime',
            'edit_speaker_id' => 'integer',
            'edit_speaker_gender' => GenderEnum::class,
            'moderator_id' => 'integer',
            'is_correct' => 'boolean',
            'split_status' => AudioSplitStatusEnum::class,
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
                ? Storage::disk($this->audioDisk())->url($this->edit_audio_filename)
                : null,
        );
    }

    /**
     * The disk this recording lives on: the one stamped at upload time, or the
     * legacy disk for rows recorded before the storage became configurable.
     */
    public function audioDisk(): string
    {
        return $this->storage_disk ?: config('audio.legacy_disk');
    }

    /**
     * Delete the files of this recording, each from the disk it actually sits
     * on: the original from the object storage it was uploaded to, the
     * converted copy from the local public disk. The row itself is untouched.
     */
    public function deleteStoredFiles(): void
    {
        if ($this->edit_audio_filename) {
            Storage::disk($this->audioDisk())->delete($this->edit_audio_filename);
        }

        if ($this->edit_converted_audio_filename) {
            Storage::disk('public')->delete($this->edit_converted_audio_filename);
        }
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function ($q) use ($search) {
            $q->where('text_id', $search);
        });
    }

    /**
     * Limit the query to audios that exceed the STT length limit and still
     * need processing (not yet split or marked unsplittable).
     */
    public function scopePendingSplit(Builder $query): Builder
    {
        return $query
            ->where('edit_converted_audio_duration', '>=', self::STT_MAX_DURATION_SAMPLES)
            ->whereNotIn('split_status', [
                AudioSplitStatusEnum::SPLIT,
                AudioSplitStatusEnum::UNSPLITTABLE,
            ]);
    }

    /**
     * Limit the query to audios within the STT length limit (or without a
     * known duration yet); excludes long audios that must be split first.
     */
    public function scopeWithinSttLimit(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->whereNull('edit_converted_audio_duration')
                ->orWhere('edit_converted_audio_duration', '<', self::STT_MAX_DURATION_SAMPLES);
        });
    }

    /**
     * Limit the query to audios recorded for the main texts of the primary
     * dataset file; the counterpart of Text::scopeMainFile() across the relation.
     */
    public function scopeMainFile(Builder $query): Builder
    {
        return $query->whereHas('text', fn (Builder $text): Builder => $text->mainFile());
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

    public function text(): BelongsTo
    {
        return $this->belongsTo(Text::class);
    }

    /**
     * The original long audio this audio was split from.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Audio::class, 'parent_audio_id');
    }

    /**
     * The shorter audios this long audio was split into.
     */
    public function splitParts(): HasMany
    {
        return $this->hasMany(Audio::class, 'parent_audio_id');
    }

    public function export(): BelongsTo
    {
        return $this->belongsTo(Export::class);
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
