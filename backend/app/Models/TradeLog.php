<?php

namespace App\Models;

use App\Enums\TradeAction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeLog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'trades_log';

    protected $fillable = [
        'league_id',
        'user_id',
        'ticker',
        'action',
        'quantity',
        'price',
        'total_amount',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => TradeAction::class,
            'quantity' => 'float',
            'price' => 'float',
            'total_amount' => 'float',
            'executed_at' => 'datetime',
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
