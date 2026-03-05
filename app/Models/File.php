<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class File extends Model
{
    const STATUS_PENDING = 'pending';

    const STATUS_PROCESSING = 'processing';

    const STATUS_COMPLETED = 'completed';

    const STATUS_SENDING = 'sending';

    const STATUS_SENT = 'sent';

    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'filename',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function texts(): HasMany
    {
        return $this->hasMany(Text::class);
    }
}
