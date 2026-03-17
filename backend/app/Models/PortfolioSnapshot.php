<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioSnapshot extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'league_id',
        'user_id',
        'total_value',
        'cash_available',
        'positions',
        'rank',
        'return_pct',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'positions' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
