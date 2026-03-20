<?php

namespace App\Http\Controllers;

use App\Enums\TradeAction;
use App\Http\Requests\ExecuteTradeRequest;
use App\Http\Resources\TradeLogResource;
use App\Models\League;
use App\Models\TradeLog;
use App\Models\WallbitKey;
use App\Services\WallbitClient;
use App\Services\WallbitVault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TradeController extends Controller
{
    public function __construct(
        private readonly WallbitClient $client,
        private readonly WallbitVault  $vault,
    ) {}

    /**
     * POST /leagues/{league}/trades
     * Execute a trade in WallBit, insert TradeLog only on success.
     * Returns 201 TradeLogResource or 422 on WallBit error.
     */
    public function execute(ExecuteTradeRequest $request, League $league): JsonResponse
    {
        if (!$league->isActive()) {
            // Allow SELL trades in finished leagues (for liquidation / cash-out)
            $isFinishedSell = $league->status === \App\Enums\LeagueStatus::Finished
                && $request->direction === 'SELL';

            if (!$isFinishedSell) {
                $message = $league->status === \App\Enums\LeagueStatus::Finished
                    ? 'Only SELL trades are allowed in finished leagues.'
                    : 'Trades can only be executed in active leagues.';

                return response()->json(['message' => $message], 403);
            }
        }

        $user = $request->user();

        // In demo mode, use a dummy key — WallbitClient returns mock data without hitting the real API.
        if (config('app.demo_mode')) {
            $apiKey = 'demo-key';
        } else {
            $wallbitKey = WallbitKey::where('user_id', $user->id)
                ->where('is_valid', true)
                ->firstOrFail();

            $apiKey = $this->vault->decrypt($wallbitKey);
        }

        try {
            $tradeData = $this->client->executeTrade(
                $apiKey,
                $request->symbol,
                $request->direction,
                $request->order_type,
                (float) $request->amount,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $shares = (float) ($tradeData['shares'] ?? 0.0);
        $price  = $shares > 0 ? (float) $request->amount / $shares : 0.0;

        $trade = TradeLog::create([
            'league_id'    => $league->id,
            'user_id'      => $user->id,
            'ticker'       => $request->symbol,
            'action'       => TradeAction::from($request->direction),
            'quantity'     => $shares,
            'price'        => $price,
            'total_amount' => (float) $request->amount,
            'executed_at'  => $tradeData['created_at'] ?? now(),
        ]);

        return (new TradeLogResource($trade))->response()->setStatusCode(201);
    }

    /**
     * GET /leagues/{league}/trades
     * Return paginated own trades for the league, ordered executed_at DESC.
     */
    /**
     * GET /leagues/{league}/assets/{symbol}
     * Preview asset price and details before trading.
     */
    public function previewAsset(Request $request, League $league, string $symbol): JsonResponse
    {
        $user = $request->user();

        // In demo mode, use a dummy key — WallbitClient returns mock data without hitting the real API.
        if (config('app.demo_mode')) {
            $apiKey = 'demo-key';
        } else {
            $wallbitKey = WallbitKey::where('user_id', $user->id)
                ->where('is_valid', true)
                ->firstOrFail();

            $apiKey = $this->vault->decrypt($wallbitKey);
        }

        try {
            $asset = $this->client->getAsset($apiKey, strtoupper($symbol));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => 'Asset not found for symbol: ' . strtoupper($symbol)], 404);
        }

        return response()->json(['data' => $asset]);
    }

    /**
     * GET /leagues/{league}/trades
     * Return paginated own trades for the league, ordered executed_at DESC.
     */
    public function index(Request $request, League $league): AnonymousResourceCollection
    {
        $trades = TradeLog::where('league_id', $league->id)
            ->where('user_id', $request->user()->id)
            ->orderBy('executed_at', 'desc')
            ->paginate(15);

        return TradeLogResource::collection($trades);
    }
}
