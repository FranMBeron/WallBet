<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids;

    protected $fillable = [
        'email',
        'username',
        'display_name',
        'password',
        'avatar_url',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function wallbitKey(): HasOne
    {
        return $this->hasOne(WallbitKey::class);
    }

    public function leagues(): BelongsToMany
    {
        return $this->belongsToMany(League::class, 'league_members')
            ->withPivot('initial_capital', 'joined_at');
    }

    public function createdLeagues(): HasMany
    {
        return $this->hasMany(League::class, 'created_by');
    }

    public function trades(): HasMany
    {
        return $this->hasMany(TradeLog::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(PortfolioSnapshot::class);
    }

    public function hasWallbitConnected(): bool
    {
        return $this->wallbitKey()->where('is_valid', true)->exists();
    }
}
