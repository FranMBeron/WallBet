<?php

namespace App\Http\Controllers;

use App\Http\Resources\PortfolioResource;
use App\Models\League;
use App\Models\User;
use App\Policies\PortfolioPolicy;
use App\Services\PortfolioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{
    public function __construct(
        private readonly PortfolioService $portfolioService,
    ) {}

    /**
     * GET /leagues/{league}/portfolio
     * Real-time portfolio for the authenticated user (or target user if policy allows).
     * Returns 200 PortfolioResource or 403.
     */
    public function show(Request $request, League $league): JsonResponse
    {
        $viewer = $request->user();

        // Allow ?user_id= to view another member's portfolio; default to own
        $targetUserId = $request->query('user_id', $viewer->id);
        $target       = $targetUserId === $viewer->id
            ? $viewer
            : User::findOrFail($targetUserId);

        $policy = new PortfolioPolicy();

        if (!$policy->viewPositions($viewer, $target, $league)) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        $portfolio = $this->portfolioService->buildPortfolio($league, $target);

        return (new PortfolioResource($portfolio))->response();
    }
}
