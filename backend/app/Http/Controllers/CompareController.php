<?php

namespace App\Http\Controllers;

use App\Enums\LeagueStatus;
use App\Enums\TradeAction;
use App\Http\Resources\CompareResource;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;
use App\Policies\PortfolioPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompareController extends Controller
{
    /**
     * GET /leagues/{league}/compare?user1=&user2=
     * 403 if active league; 422 if user1/user2 not members; 200 CompareResource.
     */
    public function index(Request $request, League $league): JsonResponse
    {
        // Gate 1: 403 if league is still active
        if ($league->status === LeagueStatus::Active) {
            return response()->json([
                'message'  => 'Comparison is only available after the league has finished.',
                'ends_at'  => $league->ends_at,
            ], 403);
        }

        $viewer = $request->user();

        // Validate user1 and user2 params
        $request->validate([
            'user1' => ['required', 'string'],
            'user2' => ['required', 'string'],
        ]);

        $user1Id = $request->query('user1');
        $user2Id = $request->query('user2');

        // Validate both users are league members
        $member1Exists = LeagueMember::where('league_id', $league->id)
            ->where('user_id', $user1Id)
            ->exists();

        if (!$member1Exists) {
            return response()->json([
                'message' => 'user1 is not a member of this league.',
                'errors'  => ['user1' => ['The selected user1 is not a member of this league.']],
            ], 422);
        }

        $member2Exists = LeagueMember::where('league_id', $league->id)
            ->where('user_id', $user2Id)
            ->exists();

        if (!$member2Exists) {
            return response()->json([
                'message' => 'user2 is not a member of this league.',
                'errors'  => ['user2' => ['The selected user2 is not a member of this league.']],
            ], 422);
        }

        $user1 = User::findOrFail($user1Id);
        $user2 = User::findOrFail($user2Id);

        // Policy check (viewCompare → delegates to viewPositions)
        $policy = new PortfolioPolicy();
        if (!$policy->viewCompare($viewer, $user1, $league)) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        // Load latest snapshot for each user
        $snap1 = DB::selectOne("
            SELECT DISTINCT ON (user_id) user_id, total_value, return_pct, positions, rank
            FROM portfolio_snapshots
            WHERE league_id = ? AND user_id = ?
            ORDER BY user_id, captured_at DESC
        ", [$league->id, $user1->id]);

        $snap2 = DB::selectOne("
            SELECT DISTINCT ON (user_id) user_id, total_value, return_pct, positions, rank
            FROM portfolio_snapshots
            WHERE league_id = ? AND user_id = ?
            ORDER BY user_id, captured_at DESC
        ", [$league->id, $user2->id]);

        // Load all trades for each user for win_rate / unique_tickers
        $trades1 = DB::select("
            SELECT ticker, action, quantity, price, total_amount
            FROM trades_log
            WHERE league_id = ? AND user_id = ?
        ", [$league->id, $user1->id]);

        $trades2 = DB::select("
            SELECT ticker, action, quantity, price, total_amount
            FROM trades_log
            WHERE league_id = ? AND user_id = ?
        ", [$league->id, $user2->id]);

        // Compute per-user metrics
        $userData1 = $this->buildUserBlock($user1, $snap1, $trades1);
        $userData2 = $this->buildUserBlock($user2, $snap2, $trades2);

        // Shared tickers
        $positions1 = $snap1 ? (is_string($snap1->positions) ? json_decode($snap1->positions, true) : (array) $snap1->positions) : [];
        $positions2 = $snap2 ? (is_string($snap2->positions) ? json_decode($snap2->positions, true) : (array) $snap2->positions) : [];

        $tickers1 = array_map(fn ($p) => is_array($p) ? ($p['ticker'] ?? '') : ($p->ticker ?? ''), $positions1);
        $tickers2 = array_map(fn ($p) => is_array($p) ? ($p['ticker'] ?? '') : ($p->ticker ?? ''), $positions2);

        $sharedTickers = array_values(array_intersect($tickers1, $tickers2));

        // Evolution: last snapshot per day for each user, aligned to shared dates
        $history1 = DB::select("
            SELECT DISTINCT ON (DATE(captured_at))
                DATE(captured_at) AS day, return_pct
            FROM portfolio_snapshots
            WHERE league_id = ? AND user_id = ?
            ORDER BY DATE(captured_at), captured_at DESC
        ", [$league->id, $user1->id]);

        $history2 = DB::select("
            SELECT DISTINCT ON (DATE(captured_at))
                DATE(captured_at) AS day, return_pct
            FROM portfolio_snapshots
            WHERE league_id = ? AND user_id = ?
            ORDER BY DATE(captured_at), captured_at DESC
        ", [$league->id, $user2->id]);

        $histMap1 = [];
        foreach ($history1 as $row) {
            $histMap1[$row->day] = (float) $row->return_pct;
        }

        $histMap2 = [];
        foreach ($history2 as $row) {
            $histMap2[$row->day] = (float) $row->return_pct;
        }

        $allDates = array_unique(array_merge(array_keys($histMap1), array_keys($histMap2)));
        sort($allDates);

        $user1Returns = [];
        $user2Returns = [];
        foreach ($allDates as $date) {
            $user1Returns[] = $histMap1[$date] ?? null;
            $user2Returns[] = $histMap2[$date] ?? null;
        }

        $evolution = [
            'dates'         => array_values($allDates),
            'user1_returns' => $user1Returns,
            'user2_returns' => $user2Returns,
        ];

        return (new CompareResource([
            'user1'          => $userData1,
            'user2'          => $userData2,
            'shared_tickers' => $sharedTickers,
            'evolution'      => $evolution,
        ]))->response();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build the per-user block for the compare response.
     */
    private function buildUserBlock(User $user, ?object $snap, array $trades): array
    {
        $positions = [];
        $totalValue = 0.0;
        $returnPct  = 0.0;

        if ($snap) {
            $totalValue = (float) $snap->total_value;
            $returnPct  = (float) $snap->return_pct;
            $rawPositions = is_string($snap->positions)
                ? json_decode($snap->positions, true)
                : (array) $snap->positions;

            foreach ($rawPositions as $pos) {
                $ticker  = is_array($pos) ? ($pos['ticker'] ?? '') : ($pos->ticker ?? '');
                $shares  = is_array($pos) ? (float) ($pos['shares'] ?? 0) : (float) ($pos->shares ?? 0);
                $value   = is_array($pos)
                    ? (float) ($pos['current_value'] ?? $pos['value'] ?? 0)
                    : (float) ($pos->current_value ?? $pos->value ?? 0);
                $weight  = $totalValue > 0 ? round($value / $totalValue * 100, 4) : 0.0;

                $positions[] = [
                    'ticker'     => $ticker,
                    'shares'     => $shares,
                    'value'      => $value,
                    'weight_pct' => $weight,
                ];
            }
        }

        $uniqueTickers = count(array_unique(array_map(fn ($t) => $t->ticker, $trades)));
        $totalTrades   = count($trades);
        $winRate       = $this->computeWinRate($trades);

        return [
            'id'             => $user->id,
            'display_name'   => $user->display_name ?? $user->name ?? $user->email,
            'return_pct'     => $returnPct,
            'total_trades'   => $totalTrades,
            'unique_tickers' => $uniqueTickers,
            'win_rate'       => $winRate,
            'positions'      => $positions,
        ];
    }

    /**
     * Compute win rate for the compare block.
     * Returns null if no SELL trades.
     */
    private function computeWinRate(array $trades): ?float
    {
        $sells = array_filter($trades, fn ($t) => $t->action === TradeAction::Sell->value || $t->action === 'SELL');

        if (empty($sells)) {
            return null;
        }

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
}
