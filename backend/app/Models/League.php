<?php

namespace App\Models;

use App\Enums\LeagueStatus;
use App\Enums\LeagueType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class League extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'description',
        'type',
        'buy_in',
        'max_participants',
        'status',
        'invite_code',
        'password',
        'is_public',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => LeagueType::class,
            'status' => LeagueStatus::class,
            'is_public' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'league_members')
            ->withPivot('initial_capital', 'joined_at');
    }

    public function leagueMembers(): HasMany
    {
        return $this->hasMany(LeagueMember::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(PortfolioSnapshot::class);
    }

    public function trades(): HasMany
    {
        return $this->hasMany(TradeLog::class);
    }

    public function isFull(): bool
    {
        return $this->members()->count() >= $this->max_participants;
    }

    public function isActive(): bool
    {
        return $this->status === LeagueStatus::Active;
    }
}
