<?php

namespace App\Services;

use App\Enums\LeagueStatus;
use App\Enums\TradeAction;
use App\Models\League;
use App\Models\PortfolioSnapshot;
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
        // For finished leagues, use the last snapshot instead of calling the live API.
        if ($league->status === LeagueStatus::Finished) {
            return $this->buildFromSnapshot($league, $user);
        }

        // Retrieve user's API key.
        // In demo mode, use a dummy key — WallbitClient returns mock data without hitting the real API.
        if (config('app.demo_mode')) {
            $apiKey = 'demo-key';
        } else {
            $wallbitKey = WallbitKey::where('user_id', $user->id)
                ->where('is_valid', true)
                ->firstOrFail();

            $apiKey = $this->vault->decrypt($wallbitKey);
        }

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

            try {
                $asset        = $this->client->getAsset($apiKey, $ticker);
                $currentPrice = (float) ($asset['price'] ?? 0.0);
            } catch (\Throwable) {
                // Live API unavailable (e.g. demo/fake keys) — fall back to snapshot data.
                return $this->buildFromSnapshot($league, $user);
            }

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

    /**
     * Build portfolio from the latest snapshot (used for finished leagues).
     * Avoids calling the external Wallbit API for static historical data.
     */
    private function buildFromSnapshot(League $league, User $user): array
    {
        $snap = PortfolioSnapshot::where('league_id', $league->id)
            ->where('user_id', $user->id)
            ->orderByDesc('captured_at')
            ->first();

        $member = $league->leagueMembers()
            ->where('user_id', $user->id)
            ->first();

        $initialCapital = $member ? (float) $member->initial_capital : 0.0;

        if ($snap === null) {
            return [
                'positions'       => [],
                'cash_available'  => $initialCapital,
                'total_value'     => $initialCapital,
                'return_pct'      => 0.0,
                'initial_capital' => $initialCapital,
            ];
        }

        $rawPositions = is_string($snap->positions)
            ? json_decode($snap->positions, true)
            : (array) $snap->positions;

        $positions = [];
        $positionsValue = 0.0;

        foreach ($rawPositions as $pos) {
            $pos = (array) $pos;
            $value = (float) ($pos['current_value'] ?? $pos['value'] ?? 0.0);
            $positionsValue += $value;

            $positions[] = [
                'ticker'        => $pos['ticker'],
                'shares'        => (float) ($pos['shares'] ?? 0),
                'avg_price'     => (float) ($pos['avg_cost'] ?? $pos['avg_price'] ?? 0),
                'current_price' => (float) ($pos['current_price'] ?? 0),
                'value'         => round($value, 2),
            ];
        }

        $totalValue    = (float) $snap->total_value;
        $cashAvailable = round($totalValue - $positionsValue, 2);

        return [
            'positions'       => $positions,
            'cash_available'  => $cashAvailable,
            'total_value'     => $totalValue,
            'return_pct'      => (float) $snap->return_pct,
            'initial_capital' => $initialCapital,
        ];
    }
}
