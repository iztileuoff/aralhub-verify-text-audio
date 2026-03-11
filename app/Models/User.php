<?php

namespace App\Models;

use App\Enums\GenderEnum;
use App\Enums\RoleEnum;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role',
        'first_name',
        'last_name',
        'phone',
        'password',
        'gender',
        'age',
        'specialization_id',
        'course',
        'is_verified',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => RoleEnum::class,
            'first_name' => 'string',
            'last_name' => 'string',
            'phone' => 'string',
            'password' => 'hashed',
            'gender' => GenderEnum::class,
            'age' => 'integer',
            'specialization_id' => 'integer',
            'course' => 'string',
            'is_verified' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function password(): Attribute
    {
        return new Attribute(
            get: null,
            set: fn ($value) => bcrypt($value),
        );
    }

    public function scopeSearch(Builder $query, $search): void
    {
        $query->where('first_name', 'like', "%$search%")
            ->orWhere('last_name', 'like', "%$search%")
            ->orWhere('phone', 'like', "%$search%");
    }

    public function specialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class);
    }

    public function editTexts(): HasMany
    {
        return $this->hasMany(Text::class, 'edit_user_id');
    }

    public function finishedEditTexts(): HasMany
    {
        return $this->hasMany(Text::class, 'edit_user_id')->whereNotNull('edit_finished_at');
    }

    public function todayFinishedEditTexts(): HasMany
    {
        return $this->hasMany(Text::class, 'edit_user_id')->whereNotNull('edit_finished_at')->whereDate('edit_finished_at', '=', now()->format('Y-m-d'));
    }

    public function actions(): HasMany
    {
        return $this->hasMany(Action::class);
    }

    public function editSpeakTexts(): HasMany
    {
        return $this->hasMany(Text::class, 'edit_speaker_id');
    }
}
