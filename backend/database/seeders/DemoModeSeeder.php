<?php

namespace Database\Seeders;

use App\Enums\LeagueStatus;
use App\Enums\LeagueType;
use App\Enums\TradeAction;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\PortfolioSnapshot;
use App\Models\TradeLog;
use App\Models\User;
use App\Models\WallbitKey;
use App\Services\WallbitVault;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoModeSeeder extends Seeder
{
    // Final return % for each user in the finished league
    private array $finalReturns = [
        'alejandra@wallbet.io' => 0.42,   // +42%
        'martin@wallbet.io'    => 0.18,   // +18%
        'demo@wallbet.io'      => 0.05,   // +5%
        'carolina@wallbet.io'  => -0.02,  // -2%
        'diego@wallbet.io'     => -0.31,  // -31%
    ];

    // Approximate stock prices (used for trades)
    private array $prices = [
        'AAPL'  => ['base' => 180.0,  'volatile' => 12.0],
        'GOOGL' => ['base' => 170.0,  'volatile' => 15.0],
        'MSFT'  => ['base' => 420.0,  'volatile' => 20.0],
        'TSLA'  => ['base' => 200.0,  'volatile' => 25.0],
        'NVDA'  => ['base' => 700.0,  'volatile' => 50.0],
    ];

    public function run(): void
    {
        $this->command?->info('Seeding demo data…');

        // ── 1. Users ──────────────────────────────────────────────────────────
        $users = $this->seedUsers();

        // ── 2. Wallbit keys ───────────────────────────────────────────────────
        $this->seedWallbitKeys($users);

        // ── 3. Leagues ────────────────────────────────────────────────────────
        [$finished, $active, $upcoming] = $this->seedLeagues($users['demo@wallbet.io']);

        // ── 4. League members ─────────────────────────────────────────────────
        $this->seedMembers($finished, $users, array_keys($users));             // all 5
        $activeMembers = ['demo@wallbet.io', 'alejandra@wallbet.io', 'martin@wallbet.io', 'carolina@wallbet.io'];
        $this->seedMembers($active, $users, $activeMembers);
        $upcomingMembers = ['demo@wallbet.io', 'martin@wallbet.io'];
        $this->seedMembers($upcoming, $users, $upcomingMembers);

        // ── 5. Trades ─────────────────────────────────────────────────────────
        $this->seedFinishedTrades($finished, $users);
        $this->seedActiveTrades($active, $users, $activeMembers);

        // ── 6. Snapshots ──────────────────────────────────────────────────────
        $this->seedFinishedSnapshots($finished, $users);
        $this->seedActiveSnapshots($active, $users, $activeMembers);

        $this->command?->info('Demo data seeded successfully.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function seedUsers(): array
    {
        $definitions = [
            'demo@wallbet.io'      => ['username' => 'demo_user',        'display_name' => 'Demo Usuario'],
            'alejandra@wallbet.io' => ['username' => 'alejandra_trades', 'display_name' => 'Alejandra Torres'],
            'martin@wallbet.io'    => ['username' => 'martin_mercados',  'display_name' => 'Martín Gómez'],
            'carolina@wallbet.io'  => ['username' => 'carolina_invert',  'display_name' => 'Carolina Ruiz'],
            'diego@wallbet.io'     => ['username' => 'diego_riesgo',     'display_name' => 'Diego López'],
        ];

        $users = [];
        foreach ($definitions as $email => $attrs) {
            $users[$email] = User::firstOrCreate(
                ['email' => $email],
                array_merge($attrs, ['password' => Hash::make('password')])
            );
        }

        return $users;
    }

    private function seedWallbitKeys(array $users): void
    {
        $vault = app(WallbitVault::class);

        foreach ($users as $email => $user) {
            if (WallbitKey::where('user_id', $user->id)->exists()) {
                continue;
            }

            $encrypted = $vault->encrypt('demo-api-key-' . $user->id);

            WallbitKey::create([
                'user_id'       => $user->id,
                'encrypted_key' => $encrypted['encrypted_key'],
                'iv'            => $encrypted['iv'],
                'auth_tag'      => $encrypted['auth_tag'],
                'is_valid'      => true,
                'connected_at'  => now(),
            ]);
        }
    }

    private function seedLeagues(User $creator): array
    {
        $finished = League::firstOrCreate(
            ['name' => 'Campeonato WallBet'],
            [
                'description'      => 'La liga inaugural de WallBet. Competencia cerrada con resultados históricos.',
                'type'             => LeagueType::Private,
                'buy_in'           => 10000,
                'max_participants' => 10,
                'status'           => LeagueStatus::Finished,
                'invite_code'      => 'WALLBET-CAMP',
                'is_public'        => true,
                'starts_at'        => now()->subDays(60),
                'ends_at'          => now()->subDays(14),
                'created_by'       => $creator->id,
            ]
        );

        $active = League::firstOrCreate(
            ['name' => 'Copa de Primavera'],
            [
                'description'      => 'La copa de la temporada. Operaciones en tiempo real, resultados en vivo.',
                'type'             => LeagueType::Private,
                'buy_in'           => 5000,
                'max_participants' => 8,
                'status'           => LeagueStatus::Active,
                'invite_code'      => 'WALLBET-COPA',
                'is_public'        => true,
                'starts_at'        => now()->subDays(7),
                'ends_at'          => now()->addDays(14),
                'created_by'       => $creator->id,
            ]
        );

        $upcoming = League::firstOrCreate(
            ['name' => 'Liga Novatos'],
            [
                'description'      => 'Liga de entrada para nuevos participantes. ¡Unite antes que arranque!',
                'type'             => LeagueType::Private,
                'buy_in'           => 1000,
                'max_participants' => 6,
                'status'           => LeagueStatus::Upcoming,
                'invite_code'      => 'WALLBET-DEMO',
                'is_public'        => false,
                'starts_at'        => now()->addDays(7),
                'ends_at'          => now()->addDays(35),
                'created_by'       => $creator->id,
            ]
        );

        return [$finished, $active, $upcoming];
    }

    private function seedMembers(League $league, array $users, array $emails): void
    {
        foreach ($emails as $email) {
            $user = $users[$email];
            LeagueMember::firstOrCreate(
                ['league_id' => $league->id, 'user_id' => $user->id],
                ['initial_capital' => $league->buy_in, 'joined_at' => $league->starts_at ?? now()]
            );
        }
    }

    // ── Trades ────────────────────────────────────────────────────────────────

    private function seedFinishedTrades(League $league, array $users): void
    {
        // Skip if already seeded
        if (TradeLog::where('league_id', $league->id)->exists()) {
            return;
        }

        $leagueStart = $league->starts_at;
        $leagueEnd   = $league->ends_at;
        $duration    = $leagueStart->diffInDays($leagueEnd);

        // Define trade scripts per user to hit target returns
        // [ticker, action, shares, day_offset, price_multiplier]
        $scripts = [
            'alejandra@wallbet.io' => [
                // Big winner: bought NVDA early, sold at peak
                ['NVDA', 'BUY',  5,  2,  0.92],
                ['AAPL', 'BUY',  20, 3,  0.95],
                ['MSFT', 'BUY',  8,  5,  0.94],
                ['NVDA', 'SELL', 5,  30, 1.35],  // +43% on NVDA
                ['GOOGL','BUY',  15, 10, 0.97],
                ['AAPL', 'SELL', 20, 35, 1.25],  // +30% on AAPL
                ['MSFT', 'SELL', 8,  38, 1.20],
                ['GOOGL','SELL', 15, 40, 1.15],
            ],
            'martin@wallbet.io' => [
                ['AAPL', 'BUY',  25, 2,  0.98],
                ['MSFT', 'BUY',  10, 4,  0.97],
                ['TSLA', 'BUY',  15, 6,  1.00],
                ['AAPL', 'SELL', 15, 25, 1.12],
                ['TSLA', 'SELL', 10, 30, 1.08],
                ['MSFT', 'SELL', 5,  35, 1.10],
                ['AAPL', 'SELL', 10, 40, 1.18],
                ['MSFT', 'SELL', 5,  42, 1.15],
            ],
            'demo@wallbet.io' => [
                ['AAPL', 'BUY',  15, 3,  1.00],
                ['GOOGL','BUY',  10, 5,  1.00],
                ['TSLA', 'BUY',  10, 8,  1.05],
                ['TSLA', 'SELL', 5,  20, 0.95],  // small loss on TSLA
                ['AAPL', 'SELL', 10, 30, 1.08],
                ['GOOGL','SELL', 5,  35, 1.10],
                ['AAPL', 'SELL', 5,  40, 1.06],
                ['GOOGL','SELL', 5,  42, 1.08],
            ],
            'carolina@wallbet.io' => [
                ['TSLA', 'BUY',  20, 2,  1.02],
                ['AAPL', 'BUY',  10, 4,  1.01],
                ['NVDA', 'BUY',  3,  7,  1.05],
                ['TSLA', 'SELL', 10, 15, 0.97],  // slightly negative
                ['NVDA', 'SELL', 3,  28, 0.98],
                ['TSLA', 'SELL', 10, 35, 0.96],
                ['AAPL', 'SELL', 10, 40, 1.02],
            ],
            'diego@wallbet.io' => [
                // Big loser: chased TSLA at peak, lost big
                ['TSLA', 'BUY',  30, 5,  1.10],  // bought high
                ['NVDA', 'BUY',  5,  6,  1.12],  // bought high
                ['TSLA', 'SELL', 20, 20, 0.80],  // sold low
                ['MSFT', 'BUY',  10, 22, 1.05],
                ['NVDA', 'SELL', 5,  25, 0.75],  // sold much lower
                ['TSLA', 'SELL', 10, 35, 0.72],  // kept selling low
                ['MSFT', 'SELL', 10, 40, 0.90],
            ],
        ];

        foreach ($scripts as $email => $trades) {
            $user = $users[$email];
            foreach ($trades as [$ticker, $actionStr, $shares, $dayOffset, $priceMult]) {
                $basePrice = $this->prices[$ticker]['base'];
                $price     = round($basePrice * $priceMult, 2);
                $tradeDate = $leagueStart->copy()->addDays(min($dayOffset, $duration - 1));

                TradeLog::create([
                    'league_id'   => $league->id,
                    'user_id'     => $user->id,
                    'ticker'      => $ticker,
                    'action'      => $actionStr === 'BUY' ? TradeAction::Buy : TradeAction::Sell,
                    'quantity'    => $shares,
                    'price'       => $price,
                    'total_amount'=> round($price * $shares, 2),
                    'executed_at' => $tradeDate->copy()->addHours(rand(9, 15))->addMinutes(rand(0, 59)),
                ]);
            }
        }
    }

    private function seedActiveTrades(League $league, array $users, array $emails): void
    {
        if (TradeLog::where('league_id', $league->id)->exists()) {
            return;
        }

        $leagueStart = $league->starts_at;

        $scripts = [
            'alejandra@wallbet.io' => [
                ['NVDA', 'BUY', 3,  1, 1.00],
                ['AAPL', 'BUY', 10, 2, 0.99],
                ['MSFT', 'BUY', 5,  3, 1.01],
            ],
            'martin@wallbet.io' => [
                ['AAPL', 'BUY', 12, 1, 1.00],
                ['TSLA', 'BUY', 8,  3, 0.98],
                ['GOOGL','BUY', 6,  5, 1.02],
            ],
            'demo@wallbet.io' => [
                ['AAPL', 'BUY', 8,  1, 1.00],
                ['MSFT', 'BUY', 4,  4, 1.00],
            ],
            'carolina@wallbet.io' => [
                ['TSLA', 'BUY', 10, 2, 1.00],
                ['AAPL', 'BUY', 5,  5, 0.99],
                ['GOOGL','BUY', 4,  6, 1.01],
            ],
        ];

        foreach ($emails as $email) {
            if (!isset($scripts[$email])) continue;
            $user = $users[$email];
            foreach ($scripts[$email] as [$ticker, $actionStr, $shares, $dayOffset, $priceMult]) {
                $basePrice = $this->prices[$ticker]['base'];
                $price     = round($basePrice * $priceMult, 2);
                $tradeDate = $leagueStart->copy()->addDays($dayOffset);

                TradeLog::create([
                    'league_id'    => $league->id,
                    'user_id'      => $user->id,
                    'ticker'       => $ticker,
                    'action'       => TradeAction::Buy,
                    'quantity'     => $shares,
                    'price'        => $price,
                    'total_amount' => round($price * $shares, 2),
                    'executed_at'  => $tradeDate->copy()->addHours(rand(9, 15))->addMinutes(rand(0, 59)),
                ]);
            }
        }
    }

    // ── Snapshots ─────────────────────────────────────────────────────────────

    private function seedFinishedSnapshots(League $league, array $users): void
    {
        if (PortfolioSnapshot::where('league_id', $league->id)->exists()) {
            return;
        }

        // Generate 35 snapshots for the finished league
        // Spread across the last 7 trading days before the league ended
        $leagueEnd = $league->ends_at;

        $timestamps = [];
        $day = 0;
        while (count($timestamps) < 35) {
            $date = $leagueEnd->copy()->subDays($day + 1);
            // Skip weekends
            if (in_array($date->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY])) {
                $day++;
                continue;
            }
            // 5 snapshots per trading day at 10:00, 11:00, 12:00, 14:00, 15:30
            foreach ([10, 11, 12, 14, 15] as $hour) {
                $timestamps[] = $date->copy()->setTime($hour, $hour === 15 ? 30 : 0);
                if (count($timestamps) >= 35) break;
            }
            $day++;
        }

        sort($timestamps);

        // For each user, interpolate value from 10000 toward final
        $finalReturns = $this->finalReturns;

        foreach ($users as $email => $user) {
            $finalReturn = $finalReturns[$email] ?? 0.0;
            $finalValue  = 10000 * (1 + $finalReturn);
            $total       = count($timestamps);

            foreach ($timestamps as $i => $ts) {
                $progress   = ($total > 1) ? ($i / ($total - 1)) : 1;
                // S-curve interpolation for more realistic progression
                $eased      = $progress < 0.5
                    ? 2 * $progress * $progress
                    : 1 - pow(-2 * $progress + 2, 2) / 2;
                $totalValue = round(10000 + ($finalValue - 10000) * $eased, 2);
                $returnPct  = round(($totalValue / 10000 - 1) * 100, 4);

                // Rank: sort users by return at this point and find position
                $rank = $this->calculateRankAtPoint($email, $eased, $finalReturns);

                // Build positions based on email and snapshot timing
                $positions = $this->buildPositions($email, $totalValue, $progress);
                $cash      = max(0, round($totalValue * 0.15, 2)); // ~15% cash remaining

                PortfolioSnapshot::create([
                    'league_id'       => $league->id,
                    'user_id'         => $user->id,
                    'total_value'     => $totalValue,
                    'cash_available'  => $cash,
                    'positions'       => $positions,
                    'rank'            => $rank,
                    'return_pct'      => $returnPct,
                    'captured_at'     => $ts,
                ]);
            }
        }
    }

    private function seedActiveSnapshots(League $league, array $users, array $emails): void
    {
        if (PortfolioSnapshot::where('league_id', $league->id)->exists()) {
            return;
        }

        // 8 snapshots: last 2 trading days (4 per day)
        $now = now();
        $timestamps = [];
        for ($d = 1; $d >= 0; $d--) {
            $date = $now->copy()->subDays($d);
            if (in_array($date->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY])) continue;
            foreach ([10, 11, 13, 15] as $hour) {
                $ts = $date->copy()->setTime($hour, 0);
                if ($ts->isFuture()) continue;
                $timestamps[] = $ts;
            }
        }

        // Partial returns for active league (small movements so far)
        $partialReturns = [
            'alejandra@wallbet.io' => 0.08,
            'martin@wallbet.io'    => 0.04,
            'demo@wallbet.io'      => 0.02,
            'carolina@wallbet.io'  => -0.01,
        ];

        foreach ($emails as $email) {
            $user        = $users[$email];
            $finalReturn = $partialReturns[$email] ?? 0.0;
            $finalValue  = 5000 * (1 + $finalReturn);
            $total       = count($timestamps);

            foreach ($timestamps as $i => $ts) {
                $progress   = ($total > 1) ? ($i / ($total - 1)) : 1;
                $totalValue = round(5000 + ($finalValue - 5000) * $progress, 2);
                $returnPct  = round(($totalValue / 5000 - 1) * 100, 4);
                $rank       = $this->calculateRankAtPoint($email, $progress, $partialReturns);

                PortfolioSnapshot::create([
                    'league_id'      => $league->id,
                    'user_id'        => $user->id,
                    'total_value'    => $totalValue,
                    'cash_available' => round($totalValue * 0.25, 2),
                    'positions'      => $this->buildActivePositions($email, $totalValue),
                    'rank'           => $rank,
                    'return_pct'     => $returnPct,
                    'captured_at'    => $ts,
                ]);
            }
        }
    }

    private function calculateRankAtPoint(string $email, float $progress, array $finalReturns): int
    {
        $currentReturn = ($finalReturns[$email] ?? 0.0) * $progress;
        $rank = 1;
        foreach ($finalReturns as $otherEmail => $otherFinal) {
            if ($otherEmail === $email) continue;
            $otherCurrent = $otherFinal * $progress;
            if ($otherCurrent > $currentReturn) {
                $rank++;
            }
        }
        return $rank;
    }

    private function buildPositions(string $email, float $totalValue, float $progress): array
    {
        $invested = $totalValue * 0.85;
        $return   = $this->finalReturns[$email] ?? 0.0;

        $tickers = match (true) {
            str_starts_with($email, 'alejandra') => ['NVDA' => 0.40, 'AAPL' => 0.35, 'MSFT' => 0.25],
            str_starts_with($email, 'martin')    => ['AAPL' => 0.45, 'MSFT' => 0.30, 'TSLA' => 0.25],
            str_starts_with($email, 'demo')      => ['AAPL' => 0.40, 'GOOGL' => 0.35, 'TSLA' => 0.25],
            str_starts_with($email, 'carolina')  => ['TSLA' => 0.50, 'AAPL' => 0.30, 'NVDA' => 0.20],
            default                              => ['TSLA' => 0.50, 'NVDA' => 0.30, 'MSFT' => 0.20],
        };

        $positions = [];
        foreach ($tickers as $ticker => $weight) {
            $value        = round($invested * $weight, 2);
            $basePrice    = $this->prices[$ticker]['base'];
            $currentPrice = round($basePrice * (1 + $return * $progress * $weight * 2), 2);
            $avgCost      = round($basePrice * (1 - $return * 0.1), 2);
            $shares       = $value > 0 && $currentPrice > 0 ? round($value / $currentPrice, 4) : 0;
            $gainLossPct  = $avgCost > 0 ? round(($currentPrice / $avgCost - 1) * 100, 2) : 0;

            $positions[] = [
                'ticker'        => $ticker,
                'shares'        => $shares,
                'avg_cost'      => $avgCost,
                'current_price' => $currentPrice,
                'current_value' => round($shares * $currentPrice, 2),
                'gain_loss_pct' => $gainLossPct,
            ];
        }

        return $positions;
    }

    private function buildActivePositions(string $email, float $totalValue): array
    {
        $invested = $totalValue * 0.75;

        $tickers = match (true) {
            str_starts_with($email, 'alejandra') => ['NVDA' => 0.50, 'AAPL' => 0.30, 'MSFT' => 0.20],
            str_starts_with($email, 'martin')    => ['AAPL' => 0.50, 'TSLA' => 0.30, 'GOOGL' => 0.20],
            str_starts_with($email, 'demo')      => ['AAPL' => 0.60, 'MSFT' => 0.40],
            default                              => ['TSLA' => 0.55, 'AAPL' => 0.25, 'GOOGL' => 0.20],
        };

        $positions = [];
        foreach ($tickers as $ticker => $weight) {
            $value        = round($invested * $weight, 2);
            $basePrice    = $this->prices[$ticker]['base'];
            $currentPrice = round($basePrice * 1.02, 2); // slight gain
            $shares       = $currentPrice > 0 ? round($value / $currentPrice, 4) : 0;

            $positions[] = [
                'ticker'        => $ticker,
                'shares'        => $shares,
                'avg_cost'      => $basePrice,
                'current_price' => $currentPrice,
                'current_value' => round($shares * $currentPrice, 2),
                'gain_loss_pct' => round(($currentPrice / $basePrice - 1) * 100, 2),
            ];
        }

        return $positions;
    }
}
