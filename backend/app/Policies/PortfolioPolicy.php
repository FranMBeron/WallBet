<?php

namespace App\Policies;

use App\Enums\LeagueStatus;
use App\Models\League;
use App\Models\User;

class PortfolioPolicy
{
    /**
     * View positions:
     * - Always allowed for own positions.
     * - Allowed for others only when the league has finished.
     */
    public function viewPositions(User $viewer, User $target, League $league): bool
    {
        if ($viewer->id === $target->id) {
            return true;
        }

        return $league->status === LeagueStatus::Finished;
    }

    /**
     * View top tickers analytics:
     * - Allowed only when the league has finished.
     */
    public function viewTopTickers(User $user, League $league): bool
    {
        return $league->status === LeagueStatus::Finished;
    }
}
