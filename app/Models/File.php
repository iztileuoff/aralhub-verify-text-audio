<?php

namespace App\Models;

use Database\Factories\FileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class File extends Model
{
    /** @use HasFactory<FileFactory> */
    use HasFactory;

    const STATUS_PENDING = 'pending';

    const STATUS_PROCESSING = 'processing';

    const STATUS_COMPLETED = 'completed';

    const STATUS_SENDING = 'sending';

    const STATUS_SENT = 'sent';

    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'filename',
        'label',
        'path',
        'mime_type',
        'size',
        'user_id',
        'status',
        'rows_total',
        'rows_imported',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'filename' => 'string',
            'label' => 'string',
            'path' => 'string',
            'mime_type' => 'string',
            'size' => 'integer',
            'user_id' => 'integer',
            'rows_total' => 'integer',
            'rows_imported' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Whether this file is the dataset the whole pipeline currently works on.
     *
     * Derived from `config('dataset.main_file_id')` rather than stored, so the
     * admin panel cannot show one dataset as active while the queues, counters
     * and reports are filtered by another.
     *
     * @return Attribute<bool, never>
     */
    protected function isActive(): Attribute
    {
        return Attribute::get(fn (): bool => $this->id === (int) config('dataset.main_file_id'));
    }

    /**
     * Limit the query to the active dataset.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereKey((int) config('dataset.main_file_id'));
    }

    /**
     * Name of the export group this dataset produces, e.g. "v3 batch 2026-08".
     * A dataset without a label falls back to its id, so the group still says
     * which dataset it came from.
     */
    public function exportGroupName(): string
    {
        $label = $this->label ?: "file {$this->id}";

        return $label.' batch '.now()->format('Y-m');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function texts(): HasMany
    {
        return $this->hasMany(Text::class);
    }
}
