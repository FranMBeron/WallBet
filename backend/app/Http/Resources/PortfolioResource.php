<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the plain array returned by PortfolioService::buildPortfolio().
 *
 * Expected $this->resource shape:
 *   positions[]      — ticker, shares, avg_price, current_price, value
 *   cash_available   — float
 *   total_value      — float
 *   return_pct       — float
 *   initial_capital  — float
 *   user             — User model (optional, passed from controller)
 */
class PortfolioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data        = $this->resource;
        $totalValue  = (float) ($data['total_value']   ?? 0.0);
        $positions   = $data['positions']               ?? [];
        $initial     = (float) ($data['initial_capital'] ?? 0.0);
        $cash        = (float) ($data['cash_available']  ?? 0.0);
        $returnPct   = (float) ($data['return_pct']      ?? 0.0);

        $mappedPositions = array_map(function (array $pos) use ($totalValue) {
            $value    = (float) ($pos['value']         ?? 0.0);
            $avgPrice = (float) ($pos['avg_price']     ?? 0.0);
            $curPrice = (float) ($pos['current_price'] ?? 0.0);
            $qty      = (float) ($pos['shares']        ?? 0.0);

            $pnl     = $qty > 0 && $avgPrice > 0 ? round(($curPrice - $avgPrice) * $qty, 2) : 0.0;
            $pnlPct  = $avgPrice > 0 ? round(($curPrice - $avgPrice) / $avgPrice * 100, 4) : 0.0;
            $weight  = $totalValue > 0 ? round($value / $totalValue * 100, 2) : 0.0;

            return [
                'ticker'        => $pos['ticker'],
                'name'          => $pos['ticker'],      // use ticker as display name
                'quantity'      => $qty,
                'avg_price'     => $avgPrice,
                'current_price' => $curPrice,
                'value'         => round($value, 2),
                'pnl'           => $pnl,
                'pnl_pct'       => $pnlPct,
                'weight_pct'    => $weight,
            ];
        }, $positions);

        $user = $data['user'] ?? null;

        return [
            'user'        => $user ? [
                'id'           => $user->id,
                'display_name' => $user->display_name ?? $user->name ?? $user->email,
            ] : null,
            'summary'     => [
                'initial_capital' => $initial,
                'total_value'     => $totalValue,
                'cash_available'  => $cash,
                'invested'        => round($totalValue - $cash, 2),
                'return_pct'      => $returnPct,
                'pnl'             => round($totalValue - $initial, 2),
            ],
            'positions'   => $mappedPositions,
            'is_visible'  => true,
        ];
    }
}
