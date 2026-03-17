<?php

namespace App\Policies;

use App\Models\League;
use App\Models\User;

class LeaguePolicy
{
    /** Any authenticated user may view the league directory. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Any authenticated user may view a league detail. */
    public function view(User $user, League $league): bool
    {
        return true;
    }

    /** Any authenticated user may create a league. */
    public function create(User $user): bool
    {
        return true;
    }

    /** Only the league creator may update it. */
    public function update(User $user, League $league): bool
    {
        return $user->id === $league->created_by;
    }

    /** Only the league creator may delete it. */
    public function delete(User $user, League $league): bool
    {
        return $user->id === $league->created_by;
    }
}
