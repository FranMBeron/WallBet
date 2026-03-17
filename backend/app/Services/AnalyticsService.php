<?php

namespace App\Services;

use App\Models\League;
use App\Models\User;
use App\Policies\PortfolioPolicy;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * Compute aggregate league analytics.
     * 2 DB queries. top_tickers null when policy gate is false.
     *
     * @return array{
     *   avg_return_pct: float|null,
     *   median_return_pct: float|null,
     *   positive_count: int,
     *   negative_count: int,
     *   returns_distribution: array<int, array{range: string, count: int}>,
     *   avg_diversification: float|null,
     *   total_trades: int,
     *   trades_per_day: array<int, array{date: string, count: int}>,
     *   top_tickers: array<int, array{ticker: string, holders: int, avg_weight: float}>|null,
     * }
     */
    public function getAnalytics(League $league, User $viewer): array
    {
        // QUERY A — latest snapshot per user
        $latestSnaps = DB::select("
            SELECT DISTINCT ON (user_id)
                user_id,
                total_value,
                return_pct,
                positions
            FROM portfolio_snapshots
            WHERE league_id = ?
            ORDER BY user_id, captured_at DESC
        ", [$league->id]);

        // QUERY B — trade counts total + per day
        $tradeCounts = DB::select("
            SELECT
                COUNT(*) AS total_trades,
                DATE(executed_at) AS day,
                COUNT(*) AS day_count
            FROM trades_log
            WHERE league_id = ?
            GROUP BY DATE(executed_at)
            ORDER BY DATE(executed_at) ASC
        ", [$league->id]);

        $totalTrades  = 0;
        $tradesPerDay = [];
        foreach ($tradeCounts as $row) {
            $totalTrades   += (int) $row->day_count;
            $tradesPerDay[] = ['date' => $row->day, 'count' => (int) $row->day_count];
        }

        if (empty($latestSnaps)) {
            return [
                'avg_return_pct'       => null,
                'median_return_pct'    => null,
                'positive_count'       => 0,
                'negative_count'       => 0,
                'returns_distribution' => [],
                'avg_diversification'  => null,
                'total_trades'         => $totalTrades,
                'trades_per_day'       => $tradesPerDay,
                'top_tickers'          => null,
            ];
        }

        $returnPcts = array_map(fn ($s) => (float) $s->return_pct, $latestSnaps);
        sort($returnPcts);

        $avgReturn    = round(array_sum($returnPcts) / count($returnPcts), 4);
        $medianReturn = $this->computeMedian($returnPcts);

        $positiveCount = count(array_filter($returnPcts, fn ($r) => $r > 0));
        $negativeCount = count(array_filter($returnPcts, fn ($r) => $r < 0));

        $returnsDistribution = $this->computeReturnsDistribution($returnPcts);

        // avg_diversification
        $diversifications = [];
        foreach ($latestSnaps as $snap) {
            $positions = is_string($snap->positions)
                ? json_decode($snap->positions, true)
                : (array) $snap->positions;

            $tickers = array_unique(array_map(
                fn ($p) => is_array($p) ? ($p['ticker'] ?? '') : ($p->ticker ?? ''),
                $positions
            ));
            $diversifications[] = count(array_filter($tickers));
        }

        $avgDiversification = count($diversifications) > 0
            ? round(array_sum($diversifications) / count($diversifications), 4)
            : null;

        // top_tickers gate
        $policy = new PortfolioPolicy();
        $topTickers = null;

        if ($policy->viewTopTickers($viewer, $league)) {
            $topTickers = $this->computeTopTickers($latestSnaps);
        }

        return [
            'avg_return_pct'       => $avgReturn,
            'median_return_pct'    => $medianReturn,
            'positive_count'       => $positiveCount,
            'negative_count'       => $negativeCount,
            'returns_distribution' => $returnsDistribution,
            'avg_diversification'  => $avgDiversification,
            'total_trades'         => $totalTrades,
            'trades_per_day'       => $tradesPerDay,
            'top_tickers'          => $topTickers,
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Compute median from a sorted array of floats.
     */
    private function computeMedian(array $sortedValues): ?float
    {
        $count = count($sortedValues);

        if ($count === 0) {
            return null;
        }

        $mid = intdiv($count, 2);

        if ($count % 2 === 0) {
            return round(($sortedValues[$mid - 1] + $sortedValues[$mid]) / 2, 4);
        }

        return round($sortedValues[$mid], 4);
    }

    /**
     * Compute returns distribution into defined buckets.
     *
     * Buckets: <-20, -20 to -10, -10 to -5, -5 to 0, 0 to 5, 5 to 10, 10 to 20, >20
     *
     * @param  float[] $returnPcts
     * @return array<int, array{range: string, count: int}>
     */
    private function computeReturnsDistribution(array $returnPcts): array
    {
        $buckets = [
            ['range' => '<-20',     'min' => null,  'max' => -20.0, 'count' => 0],
            ['range' => '-20 to -10', 'min' => -20.0, 'max' => -10.0, 'count' => 0],
            ['range' => '-10 to -5',  'min' => -10.0, 'max' => -5.0,  'count' => 0],
            ['range' => '-5 to 0',    'min' => -5.0,  'max' => 0.0,   'count' => 0],
            ['range' => '0 to 5',     'min' => 0.0,   'max' => 5.0,   'count' => 0],
            ['range' => '5 to 10',    'min' => 5.0,   'max' => 10.0,  'count' => 0],
            ['range' => '10 to 20',   'min' => 10.0,  'max' => 20.0,  'count' => 0],
            ['range' => '>20',        'min' => 20.0,  'max' => null,  'count' => 0],
        ];

        foreach ($returnPcts as $r) {
            foreach ($buckets as &$bucket) {
                $aboveMin = $bucket['min'] === null || $r >= $bucket['min'];
                $belowMax = $bucket['max'] === null || $r < $bucket['max'];

                if ($aboveMin && $belowMax) {
                    $bucket['count']++;
                    break;
                }
            }
            unset($bucket);
        }

        return array_map(
            fn ($b) => ['range' => $b['range'], 'count' => $b['count']],
            $buckets
        );
    }

    /**
     * Compute top 10 tickers by holder count from latest snapshots positions JSONB.
     *
     * @return array<int, array{ticker: string, holders: int, avg_weight: float}>
     */
    private function computeTopTickers(array $latestSnaps): array
    {
        $tickerHolders     = [];
        $tickerTotalWeight = [];

        foreach ($latestSnaps as $snap) {
            $positions  = is_string($snap->positions)
                ? json_decode($snap->positions, true)
                : (array) $snap->positions;
            $totalValue = (float) $snap->total_value;

            if (empty($positions) || $totalValue <= 0) {
                continue;
            }

            foreach ($positions as $pos) {
                $ticker   = is_array($pos) ? ($pos['ticker'] ?? null) : ($pos->ticker ?? null);
                $posValue = is_array($pos) ? (float) ($pos['value'] ?? 0) : (float) ($pos->value ?? 0);

                if (!$ticker) {
                    continue;
                }

                if (!isset($tickerHolders[$ticker])) {
                    $tickerHolders[$ticker]     = 0;
                    $tickerTotalWeight[$ticker] = 0.0;
                }

                $tickerHolders[$ticker]++;
                $tickerTotalWeight[$ticker] += $posValue / $totalValue;
            }
        }

        // Sort by holders DESC, take top 10
        arsort($tickerHolders);
        $top = array_slice($tickerHolders, 0, 10, true);

        $result = [];
        foreach ($top as $ticker => $holders) {
            $result[] = [
                'ticker'     => $ticker,
                'holders'    => $holders,
                'avg_weight' => round($tickerTotalWeight[$ticker] / $holders, 4),
            ];
        }

        return $result;
    }
}
