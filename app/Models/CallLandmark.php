<?php

namespace App\Models;

use App\Enums\CallLandmarkDecisionEnum;
use Database\Factories\CallLandmarkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A landmark a caller names (e.g. "Yurfak"), with one call as its audio.
 * Admins build the dictionary in the landmark queue; a landmark that is
 * already known is skipped for later calls, unless an admin keeps the call
 * as one more example.
 */
class CallLandmark extends Model
{
    /** @use HasFactory<CallLandmarkFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'normalized_name',
        'call_recording_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'call_recording_id' => 'integer',
            'created_by' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * The form two spellings are compared in: lower case (with Í → ı, the
     * Karakalpak pair), single spaces, no surrounding punctuation — so
     * "Yurfak", "yurfak" and " Yurfak." are one landmark.
     */
    public static function normalize(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name), " .,;:!?-'\"");

        return mb_strtolower(str_replace(['Í', 'í'], 'ı', $name));
    }

    /**
     * The call chosen as this landmark's audio.
     */
    public function primaryCall(): BelongsTo
    {
        return $this->belongsTo(CallRecording::class, 'call_recording_id');
    }

    /**
     * Every call an admin tied to this landmark, whatever the decision.
     */
    public function calls(): HasMany
    {
        return $this->hasMany(CallRecording::class, 'landmark_id');
    }

    /**
     * Calls kept as audio of this landmark: the primary one and the extra examples.
     */
    public function examples(): HasMany
    {
        return $this->calls()->whereIn('landmark_decision', [
            CallLandmarkDecisionEnum::PRIMARY,
            CallLandmarkDecisionEnum::ALSO,
        ]);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
