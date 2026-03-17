<?php

namespace App\Http\Controllers;

use App\Http\Resources\AnalyticsResource;
use App\Models\League;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
    ) {}

    /**
     * GET /leagues/{league}/analytics
     * Returns aggregate analytics for the league.
     * Returns 200 AnalyticsResource.
     */
    public function index(Request $request, League $league): JsonResponse
    {
        $viewer = $request->user();

        $data = $this->analyticsService->getAnalytics($league, $viewer);

        return (new AnalyticsResource($data))->response();
    }
}
