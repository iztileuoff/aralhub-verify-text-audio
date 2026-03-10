<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Action extends Model
{
    protected $fillable = [
        'user_id',
        'text_id',
        'old_text',
        'new_text',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'text_id' => 'integer',
            'old_text' => 'string',
            'new_text' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function text(): BelongsTo
    {
        return $this->belongsTo(Text::class);
    }
}
