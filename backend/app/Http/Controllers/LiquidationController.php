<?php

namespace App\Http\Controllers;

use App\Enums\LeagueStatus;
use App\Enums\TradeAction;
use App\Models\League;
use App\Models\PortfolioSnapshot;
use App\Models\TradeLog;
use App\Models\WallbitKey;
use App\Services\PortfolioService;
use App\Services\WallbitClient;
use App\Services\WallbitVault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LiquidationController extends Controller
{
    public function __construct(
        private readonly WallbitClient    $client,
        private readonly WallbitVault     $vault,
        private readonly PortfolioService $portfolio,
    ) {}

    /**
     * POST /leagues/{league}/liquidate
     *
     * Batch-sell all user positions in a finished league.
     * Demo mode uses WallbitClient (mock responses, no real HTTP).
     * Production uses Http::pool() for parallel execution.
     *
     * After successful sells, creates a new PortfolioSnapshot reflecting
     * the post-liquidation state so subsequent reads are accurate.
     */
    public function liquidate(Request $request, League $league): JsonResponse
    {
        if ($league->status !== LeagueStatus::Finished) {
            return response()->json(['message' => 'Liquidation is only allowed in finished leagues.'], 403);
        }

        $user = $request->user();

        // Build current portfolio from snapshot
        $portfolioData = $this->portfolio->buildPortfolio($league, $user);
        $positions     = collect($portfolioData['positions'])->filter(fn ($pos) => ($pos['shares'] ?? 0) > 0);

        if ($positions->isEmpty()) {
            return response()->json([
                'data' => [
                    'results'      => [],
                    'total_sold'   => 0,
                    'total_failed' => 0,
                ],
            ]);
        }

        $apiKey = $this->resolveApiKey($user);

        $positionsArray = $positions->values()->all();
        $results        = [];
        $totalSold      = 0;
        $totalFailed    = 0;

        if (config('app.demo_mode')) {
            foreach ($positionsArray as $pos) {
                $result    = $this->executeSellViaClient($apiKey, $league, $user, $pos);
                $results[] = $result;
                $result['status'] === 'ok' ? $totalSold++ : $totalFailed++;
            }
        } else {
            [$results, $totalSold, $totalFailed] = $this->executeSellsInParallel(
                $positionsArray, $apiKey, $league, $user,
            );
        }

        // Persist a new snapshot so the portfolio reflects liquidated positions
        if ($totalSold > 0) {
            $this->updateSnapshotAfterLiquidation($league, $user, $results);
        }

        return response()->json([
            'data' => [
                'results'      => $results,
                'total_sold'   => $totalSold,
                'total_failed' => $totalFailed,
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Resolve the WallBit API key for the given user.
     */
    private function resolveApiKey($user): string
    {
        if (config('app.demo_mode')) {
            return 'demo-key';
        }

        $wallbitKey = WallbitKey::where('user_id', $user->id)
            ->where('is_valid', true)
            ->firstOrFail();

        return $this->vault->decrypt($wallbitKey);
    }

    /**
     * Execute a single sell via WallbitClient (used in demo mode).
     */
    private function executeSellViaClient(string $apiKey, League $league, $user, array $pos): array
    {
        $ticker = $pos['ticker'];
        $amount = ($pos['shares'] ?? 0) * ($pos['current_price'] ?? $pos['avg_price'] ?? 0);

        try {
            $tradeData = $this->client->executeTrade(
                $apiKey, $ticker, 'SELL', 'MARKET', round($amount, 2),
            );

            $shares = (float) ($tradeData['shares'] ?? 0);
            $price  = $shares > 0 ? $amount / $shares : 0.0;

            TradeLog::create([
                'league_id'    => $league->id,
                'user_id'      => $user->id,
                'ticker'       => $ticker,
                'action'       => TradeAction::Sell,
                'quantity'     => $shares,
                'price'        => $price,
                'total_amount' => round($amount, 2),
                'executed_at'  => $tradeData['created_at'] ?? now(),
            ]);

            return [
                'ticker' => $ticker,
                'status' => 'ok',
                'shares' => $shares,
                'amount' => round($amount, 2),
            ];
        } catch (\RuntimeException $e) {
            Log::warning("Liquidation sell failed for {$ticker} in league {$league->id}", [
                'user_id' => $user->id,
                'ticker'  => $ticker,
                'error'   => $e->getMessage(),
            ]);

            return [
                'ticker' => $ticker,
                'status' => 'failed',
                'error'  => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute all sells in parallel via Http::pool() (production mode).
     *
     * @return array{0: array, 1: int, 2: int}  [results, totalSold, totalFailed]
     */
    private function executeSellsInParallel(array $positionsArray, string $apiKey, League $league, $user): array
    {
        $baseUrl     = config('wallbet.api_base_url');
        $results     = [];
        $totalSold   = 0;
        $totalFailed = 0;

        $responses = Http::pool(function ($pool) use ($positionsArray, $apiKey, $baseUrl) {
            foreach ($positionsArray as $pos) {
                $amount = ($pos['shares'] ?? 0) * ($pos['current_price'] ?? $pos['avg_price'] ?? 0);

                $pool->as($pos['ticker'])
                    ->withHeader('X-API-Key', $apiKey)
                    ->post("{$baseUrl}/trades", [
                        'symbol'     => $pos['ticker'],
                        'direction'  => 'SELL',
                        'order_type' => 'MARKET',
                        'amount'     => round($amount, 2),
                        'currency'   => 'USD',
                    ]);
            }
        });

        foreach ($positionsArray as $pos) {
            $ticker   = $pos['ticker'];
            $response = $responses[$ticker] ?? null;

            if ($response && $response->successful()) {
                $tradeData = $response->json('data', []);
                $shares    = (float) ($tradeData['shares'] ?? $pos['shares'] ?? 0);
                $amount    = (float) ($tradeData['amount'] ?? ($shares * ($pos['current_price'] ?? 0)));
                $price     = $shares > 0 ? $amount / $shares : 0.0;

                TradeLog::create([
                    'league_id'    => $league->id,
                    'user_id'      => $user->id,
                    'ticker'       => $ticker,
                    'action'       => TradeAction::Sell,
                    'quantity'     => $shares,
                    'price'        => $price,
                    'total_amount' => $amount,
                    'executed_at'  => $tradeData['created_at'] ?? now(),
                ]);

                $results[] = [
                    'ticker' => $ticker,
                    'status' => 'ok',
                    'shares' => $shares,
                    'amount' => round($amount, 2),
                ];
                $totalSold++;
            } else {
                $error = $response
                    ? ($response->json('message') ?? 'WallBit trade execution failed')
                    : 'No response received';

                Log::warning("Liquidation sell failed for {$ticker} in league {$league->id}", [
                    'user_id' => $user->id,
                    'ticker'  => $ticker,
                    'error'   => $error,
                ]);

                $results[] = [
                    'ticker' => $ticker,
                    'status' => 'failed',
                    'error'  => $error,
                ];
                $totalFailed++;
            }
        }

        return [$results, $totalSold, $totalFailed];
    }

    /**
     * Create a new PortfolioSnapshot reflecting the post-liquidation state.
     *
     * Successfully sold positions are removed; their proceeds are added to cash.
     * Failed positions remain unchanged in the snapshot.
     */
    private function updateSnapshotAfterLiquidation(League $league, $user, array $results): void
    {
        $snap = PortfolioSnapshot::where('league_id', $league->id)
            ->where('user_id', $user->id)
            ->orderByDesc('captured_at')
            ->first();

        if ($snap === null) {
            return;
        }

        $soldTickers = collect($results)
            ->where('status', 'ok')
            ->pluck('amount', 'ticker'); // ticker => proceeds

        $oldPositions = is_string($snap->positions)
            ? json_decode($snap->positions, true)
            : (array) $snap->positions;

        // Keep only positions that were NOT successfully sold
        $remainingPositions = [];
        $remainingValue     = 0.0;

        foreach ($oldPositions as $pos) {
            $pos    = (array) $pos;
            $ticker = $pos['ticker'] ?? '';

            if ($soldTickers->has($ticker)) {
                continue;
            }

            $remainingPositions[] = $pos;
            $remainingValue += (float) ($pos['current_value'] ?? $pos['value'] ?? 0.0);
        }

        // Calculate new cash: old cash + proceeds from sold positions
        $oldPositionsValue = collect($oldPositions)->sum(
            fn ($p) => (float) (((array) $p)['current_value'] ?? ((array) $p)['value'] ?? 0.0)
        );
        $oldCash     = (float) $snap->total_value - $oldPositionsValue;
        $newCash     = $oldCash + $soldTickers->sum();
        $newTotal    = $remainingValue + $newCash;

        $initialCapital = (float) ($league->leagueMembers()
            ->where('user_id', $user->id)
            ->value('initial_capital') ?? 0);

        $returnPct = $initialCapital > 0
            ? round(($newTotal - $initialCapital) / $initialCapital * 100, 4)
            : 0.0;

        PortfolioSnapshot::updateOrCreate(
            [
                'league_id'   => $league->id,
                'user_id'     => $user->id,
                'captured_at' => now(),
            ],
            [
                'total_value'    => round($newTotal, 2),
                'cash_available' => round($newCash, 2),
                'positions'      => $remainingPositions,
                'rank'           => $snap->rank,
                'return_pct'     => $returnPct,
            ]
        );
    }
}
