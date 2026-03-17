<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WallbitKey extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'encrypted_key',
        'iv',
        'auth_tag',
        'is_valid',
        'connected_at',
    ];

    protected $hidden = [
        'encrypted_key',
        'iv',
        'auth_tag',
    ];

    protected function casts(): array
    {
        return [
            'is_valid' => 'boolean',
            'connected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
