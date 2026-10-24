<?php

namespace App\Models;

use App\Enums\CallEventTypeEnum;
use Database\Factories\CallRecordingEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One step in the life of a call: who did what, and to whose work.
 * `user_id` is who acted, `trimmer_id` whose call it was at that moment.
 */
class CallRecordingEvent extends Model
{
    /** @use HasFactory<CallRecordingEventFactory> */
    use HasFactory;

    protected $fillable = [
        'call_recording_id',
        'user_id',
        'trimmer_id',
        'type',
        'trimmed_duration',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'call_recording_id' => 'integer',
            'user_id' => 'integer',
            'trimmer_id' => 'integer',
            'type' => CallEventTypeEnum::class,
            'trimmed_duration' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Count the events of each trimmer by type, with the summed duration of
     * the results they carry: [trimmer_id => [type => [count, samples]]].
     * Without a period it counts everything ever logged.
     *
     * @return array<int, array<string, array{count: int, samples: int}>>
     */
    public static function tallyByTrimmer(?Carbon $from = null, ?Carbon $to = null, ?int $trimmerId = null): array
    {
        $rows = self::query()
            ->whereNotNull('trimmer_id')
            ->when($trimmerId !== null, fn (Builder $query): Builder => $query->where('trimmer_id', $trimmerId))
            ->when($from !== null && $to !== null, fn (Builder $query): Builder => $query->whereBetween('created_at', [$from, $to]))
            ->selectRaw('trimmer_id, type, count(*) as events_count, coalesce(sum(trimmed_duration), 0) as samples')
            ->groupBy('trimmer_id', 'type')
            ->toBase()
            ->get();

        $tally = [];

        foreach ($rows as $row) {
            $tally[(int) $row->trimmer_id][$row->type] = [
                'count' => (int) $row->events_count,
                'samples' => (int) $row->samples,
            ];
        }

        return $tally;
    }

    public function callRecording(): BelongsTo
    {
        return $this->belongsTo(CallRecording::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trimmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trimmer_id');
    }
}
