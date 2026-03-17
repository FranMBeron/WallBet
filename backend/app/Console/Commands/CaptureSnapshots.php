<?php

namespace App\Console\Commands;

use App\Enums\LeagueStatus;
use App\Models\League;
use App\Models\PortfolioSnapshot;
use App\Services\SnapshotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CaptureSnapshots extends Command
{
    protected $signature = 'snapshots:capture';

    protected $description = 'Capture hourly portfolio snapshots for all active leagues';

    public function __construct(private readonly SnapshotService $snapshotService)
    {
        parent::__construct();
    }

    /**
     * Iterates active leagues → members → SnapshotService::capture().
     * Logs and continues on per-member failure.
     * After each league: assigns rank by total_value DESC for the current hour.
     */
    public function handle(): int
    {
        $leagues = League::where('status', LeagueStatus::Active)
            ->with('leagueMembers.user')
            ->get();

        $hour = now()->startOfHour();

        foreach ($leagues as $league) {
            foreach ($league->leagueMembers as $member) {
                try {
                    $this->snapshotService->capture($league, $member->user);
                } catch (\Throwable $e) {
                    Log::error('Snapshot capture failed', [
                        'league_id' => $league->id,
                        'user_id'   => $member->user_id,
                        'error'     => $e->getMessage(),
                    ]);
                }
            }

            // Assign rank by total_value DESC for the current hour
            $rank = 1;
            PortfolioSnapshot::where('league_id', $league->id)
                ->where('captured_at', $hour)
                ->orderByDesc('total_value')
                ->each(function (PortfolioSnapshot $snapshot) use (&$rank) {
                    $snapshot->update(['rank' => $rank++]);
                });
        }

        return Command::SUCCESS;
    }
}
