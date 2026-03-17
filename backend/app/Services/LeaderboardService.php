<?php

namespace App\Services;

use App\Enums\TradeAction;
use App\Models\League;
use App\Models\PortfolioSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeaderboardService
{
    /**
     * Build ranked leaderboard for the league.
     * 3 DB queries total (latest snaps, prev snaps, all trades). No N+1.
     *
     * @param string|null $sortBy Column to sort by; defaults to 'return_pct'
     * @return array{
     *   leaderboard: array<int, array{
     *     rank: int, rank_change: int,
     *     user: array{id: string, display_name: string},
     *     return_pct: float, total_value: float, pnl: float,
     *     total_trades: int, unique_tickers: int,
     *     best_trade: array{ticker: string, return_pct: float}|null,
     *     win_rate: float|null,
     *     risk_score: 'Low'|'Medium'|'High',
     *   }>,
     *   my_rank: int|null,
     * }
     */
    public function getLeaderboard(League $league, User $viewer, ?string $sortBy = null): array
    {
        $sortBy = $sortBy ?? 'return_pct';

        // QUERY A — latest snapshot per user (DISTINCT ON)
        $latestSnaps = DB::select("
            SELECT DISTINCT ON (user_id) id, user_id, total_value, return_pct, positions, rank, captured_at
            FROM portfolio_snapshots
            WHERE league_id = ?
            ORDER BY user_id, captured_at DESC
        ", [$league->id]);

        $latestByUser = [];
        foreach ($latestSnaps as $snap) {
            $latestByUser[$snap->user_id] = $snap;
        }

        // QUERY B — 2nd most recent snapshot per user (for rank_change)
        // Use ROW_NUMBER to get the second row per user
        $prevSnaps = DB::select("
            SELECT user_id, rank
            FROM (
                SELECT user_id, rank, ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY captured_at DESC) AS rn
                FROM portfolio_snapshots
                WHERE league_id = ?
            ) sub
            WHERE rn = 2
        ", [$league->id]);

        $prevByUser = [];
        foreach ($prevSnaps as $snap) {
            $prevByUser[$snap->user_id] = $snap;
        }

        // QUERY C — all trades for league (single query, group in PHP)
        $allTrades = DB::select("
            SELECT user_id, ticker, action, quantity, price, total_amount
            FROM trades_log
            WHERE league_id = ?
        ", [$league->id]);

        $tradesByUser = [];
        foreach ($allTrades as $trade) {
            $tradesByUser[$trade->user_id][] = $trade;
        }

        // Load all members
        $members = $league->members()->get();

        // Build rows — only for members who have a snapshot
        $rows = [];
        foreach ($members as $member) {
            $snap = $latestByUser[$member->id] ?? null;

            if ($snap === null) {
                continue;
            }

            $trades      = $tradesByUser[$member->id] ?? [];
            $initialCapital = (float) $league->leagueMembers()
                ->where('user_id', $member->id)
                ->value('initial_capital');

            $totalValue = (float) $snap->total_value;
            $returnPct  = (float) $snap->return_pct;
            $pnl        = $initialCapital > 0 ? round($totalValue - $initialCapital, 2) : 0.0;

            $positions  = is_string($snap->positions) ? json_decode($snap->positions, true) : (array) $snap->positions;

            $rows[] = [
                '_user_id'       => $member->id,
                '_sort_return'   => $returnPct,
                'user'           => [
                    'id'           => $member->id,
                    'display_name' => $member->display_name ?? $member->name ?? $member->email,
                ],
                'return_pct'     => $returnPct,
                'total_value'    => $totalValue,
                'pnl'            => $pnl,
                'total_trades'   => count($trades),
                'unique_tickers' => $this->computeUniqueTickers($trades),
                'best_trade'     => $this->computeBestTrade($trades),
                'win_rate'       => $this->computeWinRate($trades),
                'risk_score'     => $this->computeRiskScore($positions, $totalValue),
                '_prev_rank'     => isset($prevByUser[$member->id]) ? (int) $prevByUser[$member->id]->rank : null,
            ];
        }

        // Sort by requested column DESC
        $validColumns = ['return_pct', 'total_value', 'pnl', 'total_trades', 'unique_tickers', 'win_rate'];
        if (!in_array($sortBy, $validColumns, true)) {
            $sortBy = 'return_pct';
        }

        usort($rows, function ($a, $b) use ($sortBy) {
            $aVal = $a[$sortBy] ?? 0;
            $bVal = $b[$sortBy] ?? 0;
            return $bVal <=> $aVal;
        });

        // Assign ranks and compute rank_change
        $myRank = null;
        foreach ($rows as $i => &$row) {
            $currentRank     = $i + 1;
            $row['rank']     = $currentRank;
            $prevRank        = $row['_prev_rank'];
            $row['rank_change'] = $prevRank !== null ? $prevRank - $currentRank : 0;

            if ($row['_user_id'] === $viewer->id) {
                $myRank = $currentRank;
            }

            // Clean internal keys
            unset($row['_user_id'], $row['_sort_return'], $row['_prev_rank']);
        }
        unset($row);

        return [
            'my_rank'     => $myRank,
            'leaderboard' => $rows,
        ];
    }

    /**
     * Build history aligned to shared dates[] array.
     * 1 DB query. Null-fills missing dates per user.
     *
     * @return array{
     *   dates: string[],
     *   participants: array<int, array{
     *     user: array{id: string, display_name: string},
     *     ranks: (int|null)[],
     *     returns: (float|null)[],
     *   }>,
     * }
     */
    public function getHistory(League $league): array
    {
        // Last snapshot per user per calendar day using DISTINCT ON
        $rows = DB::select("
            SELECT DISTINCT ON (user_id, DATE(captured_at))
                user_id,
                rank,
                return_pct,
                DATE(captured_at) AS day
            FROM portfolio_snapshots
            WHERE league_id = ?
            ORDER BY user_id, DATE(captured_at), captured_at DESC
        ", [$league->id]);

        if (empty($rows)) {
            return ['dates' => [], 'participants' => []];
        }

        // Build sorted unique dates index
        $allDates = array_unique(array_map(fn ($r) => $r->day, $rows));
        sort($allDates);
        $dateIndex = array_values($allDates);

        // Build $byUser[user_id][date] = {rank, return_pct}
        $byUser = [];
        foreach ($rows as $row) {
            $byUser[$row->user_id][$row->day] = [
                'rank'       => (int) $row->rank,
                'return_pct' => (float) $row->return_pct,
            ];
        }

        // Load members
        $members = $league->members()->get()->keyBy('id');

        // Collect user_ids that appear in snapshots
        $userIds = array_keys($byUser);

        $participants = [];
        foreach ($userIds as $userId) {
            $member = $members[$userId] ?? null;
            $ranks   = [];
            $returns = [];

            foreach ($dateIndex as $date) {
                if (isset($byUser[$userId][$date])) {
                    $ranks[]   = $byUser[$userId][$date]['rank'];
                    $returns[] = $byUser[$userId][$date]['return_pct'];
                } else {
                    $ranks[]   = null;
                    $returns[] = null;
                }
            }

            $participants[] = [
                'user'    => [
                    'id'           => $userId,
                    'display_name' => $member
                        ? ($member->display_name ?? $member->name ?? $member->email)
                        : $userId,
                ],
                'ranks'   => $ranks,
                'returns' => $returns,
            ];
        }

        return [
            'dates'        => $dateIndex,
            'participants' => $participants,
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Count unique tickers across all trades.
     */
    private function computeUniqueTickers(array $trades): int
    {
        $tickers = array_unique(array_map(fn ($t) => $t->ticker, $trades));
        return count($tickers);
    }

    /**
     * Compute win rate: % of SELL trades where sell_price > avg BUY price for that ticker.
     * Returns null if no SELL trades.
     *
     * @return float|null
     */
    private function computeWinRate(array $trades): ?float
    {
        $sells = array_filter($trades, fn ($t) => $t->action === TradeAction::Sell->value || $t->action === 'SELL');

        if (empty($sells)) {
            return null;
        }

        // Group BUY trades by ticker to compute avg buy price
        $buysByTicker = [];
        foreach ($trades as $trade) {
            if ($trade->action === TradeAction::Buy->value || $trade->action === 'BUY') {
                $ticker = $trade->ticker;
                if (!isset($buysByTicker[$ticker])) {
                    $buysByTicker[$ticker] = ['total_amount' => 0.0, 'quantity' => 0.0];
                }
                $buysByTicker[$ticker]['total_amount'] += (float) $trade->total_amount;
                $buysByTicker[$ticker]['quantity']     += (float) $trade->quantity;
            }
        }

        $profitable = 0;
        $total      = 0;

        foreach ($sells as $sell) {
            $ticker = $sell->ticker;
            $avgBuy = isset($buysByTicker[$ticker]) && $buysByTicker[$ticker]['quantity'] > 0
                ? $buysByTicker[$ticker]['total_amount'] / $buysByTicker[$ticker]['quantity']
                : 0.0;

            if ((float) $sell->price > $avgBuy) {
                $profitable++;
            }
            $total++;
        }

        return $total > 0 ? round($profitable / $total * 100, 2) : null;
    }

    /**
     * Compute best trade: highest (sell_price - avg_buy_price) / avg_buy_price * 100.
     * Returns null if no SELL trades.
     *
     * @return array{ticker: string, return_pct: float}|null
     */
    private function computeBestTrade(array $trades): ?array
    {
        $sells = array_filter($trades, fn ($t) => $t->action === TradeAction::Sell->value || $t->action === 'SELL');

        if (empty($sells)) {
            return null;
        }

        // Group BUY trades by ticker
        $buysByTicker = [];
        foreach ($trades as $trade) {
            if ($trade->action === TradeAction::Buy->value || $trade->action === 'BUY') {
                $ticker = $trade->ticker;
                if (!isset($buysByTicker[$ticker])) {
                    $buysByTicker[$ticker] = ['total_amount' => 0.0, 'quantity' => 0.0];
                }
                $buysByTicker[$ticker]['total_amount'] += (float) $trade->total_amount;
                $buysByTicker[$ticker]['quantity']     += (float) $trade->quantity;
            }
        }

        $best = null;

        foreach ($sells as $sell) {
            $ticker = $sell->ticker;
            $avgBuy = isset($buysByTicker[$ticker]) && $buysByTicker[$ticker]['quantity'] > 0
                ? $buysByTicker[$ticker]['total_amount'] / $buysByTicker[$ticker]['quantity']
                : 0.0;

            if ($avgBuy <= 0) {
                continue;
            }

            $returnPct = ($sell->price - $avgBuy) / $avgBuy * 100;

            if ($best === null || $returnPct > $best['return_pct']) {
                $best = [
                    'ticker'     => $ticker,
                    'return_pct' => round($returnPct, 4),
                ];
            }
        }

        return $best;
    }

    /**
     * Compute risk score from positions JSONB and total portfolio value.
     * Top position weight: <33% = Low, 33-60% = Medium, >60% = High.
     *
     * @param  array  $positions  Array of position objects/arrays with 'value' key
     * @param  float  $totalValue Portfolio total value
     * @return 'Low'|'Medium'|'High'
     */
    private function computeRiskScore(array $positions, float $totalValue): string
    {
        if (empty($positions) || $totalValue <= 0) {
            return 'Low';
        }

        $maxValue = 0.0;
        foreach ($positions as $pos) {
            $posValue = is_array($pos) ? (float) ($pos['value'] ?? 0) : (float) ($pos->value ?? 0);
            if ($posValue > $maxValue) {
                $maxValue = $posValue;
            }
        }

        $topWeight = $maxValue / $totalValue * 100;

        if ($topWeight < 33) {
            return 'Low';
        } elseif ($topWeight <= 60) {
            return 'Medium';
        } else {
            return 'High';
        }
    }
}
