<?php

namespace App\Models;

use App\Enums\CallEventTypeEnum;
use App\Enums\CallSkipReasonEnum;
use App\Enums\CallStatusEnum;
use Closure;
use Database\Factories\CallRecordingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A phone call to be trimmed and transcribed by a trimmer.
 *
 * Deliberately kept apart from Text/Audio: those are scoped to the active
 * dataset and gated by the export pipeline, and calls must not show up in
 * either (docs/call-trim-plan.md).
 */
class CallRecording extends Model
{
    /** @use HasFactory<CallRecordingFactory> */
    use HasFactory;

    /**
     * Audio formats accepted from the customer's upload link — wider than what
     * the browser editor can open, so nothing is lost on the way in;
     * calls:register then reports the formats that need converting first.
     */
    public const UPLOAD_AUDIO_EXTENSIONS = ['mp3', 'wav', 'ogg', 'oga', 'opus', 'm4a', 'aac', 'flac', 'webm', 'amr', 'gsm', 'wma', '3gp', 'mp4'];

    /** Archives (and multi-volume parts) accepted from the upload link; valid as both a PHP and a JS regex body. */
    public const UPLOAD_ARCHIVE_PATTERN = '\.(rar|zip|7z|r\d{2}|z\d{2}|\d{3})$';

    /** Batch (delivery) names: a folder name in the bucket. */
    public const UPLOAD_BATCH_PATTERN = '[A-Za-z0-9][A-Za-z0-9_-]{0,49}';

    /** Archive part size. R2 wants every part but the last to be the same size, so it never changes mid-upload. */
    public const UPLOAD_PART_BYTES = 64 * 1024 * 1024;

    public const UPLOAD_MAX_FILE_BYTES = 100 * 1024 * 1024;

    public const UPLOAD_MAX_ARCHIVE_BYTES = 50 * 1024 * 1024 * 1024;

    /**
     * Where the audio files of a delivery go — the folder calls:register reads.
     */
    public static function sourcePrefix(string $batch): string
    {
        return 'source/'.$batch;
    }

    /**
     * Where uploaded archives wait until they are unpacked on the server.
     */
    public static function archivePrefix(string $batch): string
    {
        return 'archives/'.$batch;
    }

    protected $fillable = [
        'batch',
        'original_filename',
        'source_path',
        'size_bytes',
        'status',
        'trimmer_id',
        'assigned_at',
        'source_duration_ms',
        'trim_start_ms',
        'trim_end_ms',
        'trimmed_path',
        'trimmed_duration',
        'transcript',
        'normalized_transcript',
        'tokenized_transcript',
        'submitted_at',
        'rejections_count',
        'reject_reason',
        'skip_reason',
        'skip_comment',
        'moderator_id',
        'moderated_at',
        'exported_at',
        'export_id',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'status' => CallStatusEnum::class,
            'trimmer_id' => 'integer',
            'assigned_at' => 'datetime',
            'source_duration_ms' => 'integer',
            'trim_start_ms' => 'integer',
            'trim_end_ms' => 'integer',
            'trimmed_duration' => 'integer',
            'submitted_at' => 'datetime',
            'rejections_count' => 'integer',
            'skip_reason' => CallSkipReasonEnum::class,
            'moderator_id' => 'integer',
            'moderated_at' => 'datetime',
            'exported_at' => 'datetime',
            'export_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * The disk every call file lives on.
     */
    public static function disk(): string
    {
        return config('calls.disk');
    }

    /**
     * Moment before which an unsubmitted lock counts as abandoned.
     */
    public static function lockExpiredBefore(): Carbon
    {
        return now()->subMinutes(config('calls.lock_ttl_minutes'));
    }

    /**
     * Calls that can be handed to any trimmer: never taken, or taken and
     * abandoned. A call that came back from moderation stays with its
     * trimmer even when the lock runs out — the rejection is theirs to fix.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->where('status', CallStatusEnum::PENDING)
                ->orWhere(function (Builder $abandoned): void {
                    $abandoned->where('status', CallStatusEnum::IN_PROGRESS)
                        ->where('rejections_count', 0)
                        ->where('assigned_at', '<', self::lockExpiredBefore());
                });
        });
    }

    /**
     * Whether the given trimmer is the one working on this call right now.
     */
    public function isHeldBy(int $userId): bool
    {
        return $this->status === CallStatusEnum::IN_PROGRESS && $this->trimmer_id === $userId;
    }

    /**
     * Apply a change to this call under a row lock, but only while the given
     * trimmer still holds it — between the check in a controller and the
     * write, an expired lock may have handed the call to someone else.
     * Refreshes this instance on success.
     *
     * @param  Closure(CallRecording): void  $change
     */
    public function changeWhileHeldBy(int $userId, Closure $change): bool
    {
        $changed = DB::transaction(function () use ($userId, $change): bool {
            $locked = self::query()->lockForUpdate()->find($this->id);

            if (! $locked?->isHeldBy($userId)) {
                return false;
            }

            $change($locked);

            return true;
        });

        $this->refresh();

        return $changed;
    }

    /**
     * Apply a moderation decision under a row lock, only while the call is
     * still waiting for one, so two moderators cannot both decide it.
     * Refreshes this instance on success.
     *
     * @param  Closure(CallRecording): void  $change
     */
    public function changeWhileSubmitted(Closure $change): bool
    {
        $changed = DB::transaction(function () use ($change): bool {
            $locked = self::query()->lockForUpdate()->find($this->id);

            if ($locked?->status !== CallStatusEnum::SUBMITTED) {
                return false;
            }

            $change($locked);

            return true;
        });

        $this->refresh();

        return $changed;
    }

    /**
     * Short-lived URL of the original recording.
     */
    public function sourceUrl(): string
    {
        return Storage::disk(self::disk())->temporaryUrl($this->source_path, $this->urlExpiresAt());
    }

    /**
     * Short-lived URL of the trimmed result, once there is one.
     */
    public function trimmedUrl(): ?string
    {
        return $this->trimmed_path
            ? Storage::disk(self::disk())->temporaryUrl($this->trimmed_path, $this->urlExpiresAt())
            : null;
    }

    public function urlExpiresAt(): Carbon
    {
        return now()->addMinutes(config('calls.url_ttl_minutes'));
    }

    /**
     * Log a step of this call. The trimmer is stamped on the event so the
     * statistics credit approvals and rejections to the one whose work it
     * was, not to the moderator who clicked the button.
     */
    public function logEvent(CallEventTypeEnum $type, ?int $userId, ?string $comment = null): CallRecordingEvent
    {
        return $this->events()->create([
            'user_id' => $userId,
            'trimmer_id' => $this->trimmer_id,
            'type' => $type,
            'trimmed_duration' => $this->trimmed_duration,
            'comment' => $comment,
        ]);
    }

    public function trimmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trimmer_id');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    public function export(): BelongsTo
    {
        return $this->belongsTo(Export::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CallRecordingEvent::class);
    }
}
