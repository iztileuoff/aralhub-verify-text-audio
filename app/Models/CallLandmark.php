<?php

namespace App\Models;

use Database\Factories\CallLandmarkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A landmark a caller names ("Yurfak", "flag"…). The call an admin created
 * it from is its audio; later calls naming it are linked as duplicates, so
 * the dictionary lists each landmark once with how often it was heard.
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
     * The form two spellings are compared in (agreed with the frontend,
     * 2026-10-27): trimmed, single spaces, lower case, ё → е, and the
     * Karakalpak Í/í → ı; surrounding punctuation dropped. Any alphabet.
     * "Юрфак", " юрфак ", "ЮРФАК." are one landmark.
     */
    public static function normalize(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name), " .,;:!?'\"");

        return strtr(mb_strtolower(str_replace('Í', 'ı', $name)), ['ё' => 'е', 'í' => 'ı']);
    }

    /**
     * The call this landmark was created from.
     */
    public function primaryCall(): BelongsTo
    {
        return $this->belongsTo(CallRecording::class, 'call_recording_id');
    }

    /**
     * Every call tied to this landmark: the one it was created from and the duplicates.
     */
    public function calls(): HasMany
    {
        return $this->hasMany(CallRecording::class, 'landmark_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
