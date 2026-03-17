<?php

namespace App\Services;

use App\Models\League;
use App\Models\PortfolioSnapshot;
use App\Models\User;

class SnapshotService
{
    public function __construct(
        private readonly PortfolioService $portfolioService,
    ) {}

    /**
     * Capture (or update) the snapshot for $user in $league for the current UTC hour.
     * Uses updateOrCreate keyed on (league_id, user_id, captured_at=startOfHour()).
     *
     * @throws \RuntimeException (propagated from PortfolioService / WallbitClient)
     */
    public function capture(League $league, User $user): PortfolioSnapshot
    {
        $portfolio = $this->portfolioService->buildPortfolio($league, $user);

        $hour = now()->startOfHour();

        return PortfolioSnapshot::updateOrCreate(
            [
                'league_id'   => $league->id,
                'user_id'     => $user->id,
                'captured_at' => $hour,
            ],
            [
                'total_value'    => $portfolio['total_value'],
                'cash_available' => $portfolio['cash_available'],
                'positions'      => $portfolio['positions'],
                'return_pct'     => $portfolio['return_pct'],
            ]
        );
    }
}
