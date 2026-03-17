<?php

namespace App\Console\Commands;

use App\Enums\LeagueStatus;
use App\Models\League;
use Illuminate\Console\Command;

class UpdateLeagueStatus extends Command
{
    protected $signature = 'leagues:update-status';

    protected $description = 'Transition league statuses based on starts_at / ends_at';

    public function handle(): int
    {
        // Transition upcoming → active
        League::where('status', LeagueStatus::Upcoming)
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', now())
            ->update(['status' => LeagueStatus::Active]);

        // Transition active → finished
        League::where('status', LeagueStatus::Active)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->update(['status' => LeagueStatus::Finished]);

        return Command::SUCCESS;
    }
}
