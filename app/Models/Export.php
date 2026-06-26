<?php

namespace App\Models;

use Database\Factories\ExportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Export extends Model
{
    /** @use HasFactory<ExportFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'filename',
        'exported_count',
    ];

    protected function casts(): array
    {
        return [
            'exported_count' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function audios(): HasMany
    {
        return $this->hasMany(Audio::class);
    }
}
