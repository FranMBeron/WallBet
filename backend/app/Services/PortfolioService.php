<?php

namespace App\Services;

use App\Enums\TradeAction;
use App\Models\League;
use App\Models\User;
use App\Models\WallbitKey;

class PortfolioService
{
    public function __construct(
        private readonly WallbitClient $client,
        private readonly WallbitVault  $vault,
    ) {}

    /**
     * Reconstruct the virtual league portfolio for $user.
     * Queries trades_log, groups by ticker, fetches current prices via WallbitClient.
     *
     * @return array{
     *     positions: array<int, array{ticker: string, shares: float, avg_price: float, current_price: float, value: float}>,
     *     cash_available: float,
     *     total_value: float,
     *     return_pct: float,
     *     initial_capital: float,
     * }
     * @throws \RuntimeException if WallbitClient throws for any position
     */
    public function buildPortfolio(League $league, User $user): array
    {
        // Retrieve user's API key — throws ModelNotFoundException if none exists
        $wallbitKey = WallbitKey::where('user_id', $user->id)
            ->where('is_valid', true)
            ->firstOrFail();

        $apiKey = $this->vault->decrypt($wallbitKey);

        // Get initial capital from league membership
        $member = $league->leagueMembers()
            ->where('user_id', $user->id)
            ->first();

        $initialCapital = $member ? (float) $member->initial_capital : 0.0;

        // Fetch all trades for this user in this league
        $trades = $league->trades()
            ->where('user_id', $user->id)
            ->get();

        // Group by ticker, track net shares and weighted avg cost
        $byTicker = [];
        $netSpend  = 0.0; // total BUY spend minus SELL proceeds

        foreach ($trades as $trade) {
            $ticker = $trade->ticker;

            if (!isset($byTicker[$ticker])) {
                $byTicker[$ticker] = [
                    'buy_shares'  => 0.0,
                    'buy_cost'    => 0.0,
                    'sell_shares' => 0.0,
                ];
            }

            if ($trade->action === TradeAction::Buy) {
                $byTicker[$ticker]['buy_shares'] += (float) $trade->quantity;
                $byTicker[$ticker]['buy_cost']   += (float) $trade->total_amount;
                $netSpend += (float) $trade->total_amount;
            } else {
                $byTicker[$ticker]['sell_shares'] += (float) $trade->quantity;
                $netSpend -= (float) $trade->total_amount;
            }
        }

        // Build open positions (net_shares > 0) and fetch current prices
        $positions      = [];
        $positionsValue = 0.0;

        foreach ($byTicker as $ticker => $data) {
            $netShares = $data['buy_shares'] - $data['sell_shares'];

            if ($netShares <= 0) {
                continue;
            }

            $avgPrice = $data['buy_shares'] > 0
                ? $data['buy_cost'] / $data['buy_shares']
                : 0.0;

            $asset        = $this->client->getAsset($apiKey, $ticker);
            $currentPrice = (float) ($asset['price'] ?? 0.0);

            $value          = $netShares * $currentPrice;
            $positionsValue += $value;

            $positions[] = [
                'ticker'        => $ticker,
                'shares'        => $netShares,
                'avg_price'     => round($avgPrice, 6),
                'current_price' => $currentPrice,
                'value'         => round($value, 2),
            ];
        }

        $cashAvailable = $initialCapital - $netSpend;
        $totalValue    = $positionsValue + $cashAvailable;
        $returnPct     = $initialCapital > 0
            ? round(($totalValue - $initialCapital) / $initialCapital * 100, 4)
            : 0.0;

        return [
            'positions'       => $positions,
            'cash_available'  => round($cashAvailable, 2),
            'total_value'     => round($totalValue, 2),
            'return_pct'      => $returnPct,
            'initial_capital' => $initialCapital,
        ];
    }
}
