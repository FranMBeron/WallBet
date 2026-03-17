<?php

namespace App\Console\Commands;

use App\Models\League;
use App\Models\LeagueMember;
use App\Models\PortfolioSnapshot;
use App\Models\TradeLog;
use App\Models\User;
use App\Models\WallbitKey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class DemoReset extends Command
{
    protected $signature = 'demo:reset';
    protected $description = 'Wipe all demo data and re-seed it. Safe to run repeatedly.';

    public function handle(): int
    {
        if (!config('app.demo_mode')) {
            $this->error('APP_DEMO_MODE is not enabled. Aborting reset.');
            return self::FAILURE;
        }

        $this->info('Resetting demo data…');

        DB::transaction(function () {
            // Find demo users
            $demoEmails = [
                'demo@wallbet.io',
                'alejandra@wallbet.io',
                'martin@wallbet.io',
                'carolina@wallbet.io',
                'diego@wallbet.io',
            ];

            $demoUserIds = User::whereIn('email', $demoEmails)->pluck('id');

            if ($demoUserIds->isEmpty()) {
                $this->line('No demo users found — skipping wipe, running seeder.');
                return;
            }

            // Delete in FK-safe order
            $demoLeagueIds = League::whereIn('created_by', $demoUserIds)->pluck('id');

            PortfolioSnapshot::whereIn('league_id', $demoLeagueIds)->delete();
            TradeLog::whereIn('league_id', $demoLeagueIds)->delete();
            LeagueMember::whereIn('league_id', $demoLeagueIds)->delete();
            League::whereIn('id', $demoLeagueIds)->delete();
            WallbitKey::whereIn('user_id', $demoUserIds)->delete();

            // Revoke all Sanctum tokens for demo users
            DB::table('personal_access_tokens')
                ->whereIn('tokenable_id', $demoUserIds)
                ->delete();

            User::whereIn('id', $demoUserIds)->delete();
        });

        $this->info('Demo data wiped. Re-seeding…');

        Artisan::call('db:seed', ['--class' => 'DemoModeSeeder', '--force' => true]);

        $this->info('Demo reset complete.');

        return self::SUCCESS;
    }
}
