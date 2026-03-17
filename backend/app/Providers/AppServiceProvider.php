<?php

namespace App\Providers;

use App\Models\League;
use App\Models\LeagueMember;
use App\Policies\LeaguePolicy;
use App\Policies\PortfolioPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(League::class, LeaguePolicy::class);
        Gate::policy(LeagueMember::class, PortfolioPolicy::class);
    }
}
