<?php

namespace App\Http\Controllers;

use App\Http\Resources\LeaderboardResource;
use App\Models\League;
use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function __construct(
        private readonly LeaderboardService $leaderboardService,
    ) {}

    /**
     * GET /leagues/{league}/leaderboard[?sort_by=]
     * Returns ranked leaderboard for the league.
     * Returns 200 LeaderboardResource.
     */
    public function index(Request $request, League $league): JsonResponse
    {
        $sortBy = $request->query('sort_by');
        $viewer = $request->user();

        $data = $this->leaderboardService->getLeaderboard($league, $viewer, $sortBy);

        return (new LeaderboardResource($data))->response();
    }

    /**
     * GET /leagues/{league}/leaderboard/history
     * Returns date-aligned history for all participants.
     * Returns 200 with {dates, participants}.
     */
    public function history(Request $request, League $league): JsonResponse
    {
        $data = $this->leaderboardService->getHistory($league);

        return response()->json(['data' => $data]);
    }
}
