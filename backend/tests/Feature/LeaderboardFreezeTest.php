<?php

namespace Tests\Feature;

use App\Enums\LeagueStatus;
use App\Enums\TradeAction;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\PortfolioSnapshot;
use App\Models\TradeLog;
use App\Models\User;
use App\Services\LeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeaderboardFreezeTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createUserWithToken(): array
    {
        $user  = User::factory()->create();
        $token = $user->createToken('auth')->plainTextToken;
        return [$user, $token];
    }

    private function joinLeague(User $user, League $league, float $capital = 1000.0): LeagueMember
    {
        return LeagueMember::create([
            'league_id'       => $league->id,
            'user_id'         => $user->id,
            'initial_capital' => $capital,
            'joined_at'       => now(),
        ]);
    }

    private function createSnapshot(
        League $league,
        User $user,
        float $totalValue = 1100.0,
        float $returnPct = 10.0,
        int $rank = 1,
        array $positions = [],
        ?string $capturedAt = null,
    ): PortfolioSnapshot {
        return PortfolioSnapshot::create([
            'league_id'      => $league->id,
            'user_id'        => $user->id,
            'total_value'    => $totalValue,
            'cash_available' => 100.0,
            'positions'      => $positions,
            'rank'           => $rank,
            'return_pct'     => $returnPct,
            'captured_at'    => $capturedAt ?? now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Post-finish trades excluded from leaderboard stats
    // -------------------------------------------------------------------------

    #[Test]
    public function post_finish_sells_are_excluded_from_total_trades(): void
    {
        [$viewer, $token] = $this->createUserWithToken();
        $creator = User::factory()->create();

        // Finished league: started 20 days ago, ended 2 days ago
        $league = League::factory()->create([
            'status'     => LeagueStatus::Finished,
            'starts_at'  => now()->subDays(20),
            'ends_at'    => now()->subDays(2),
            'created_by' => $creator->id,
        ]);

        $this->joinLeague($viewer, $league);
        $this->createSnapshot($league, $viewer, 1100.0, 10.0, 1);

        // Trade DURING league period (should count)
        TradeLog::factory()->buy()->create([
            'league_id'    => $league->id,
            'user_id'      => $viewer->id,
            'ticker'       => 'AAPL',
            'quantity'     => 5.0,
            'price'        => 150.0,
            'total_amount' => 750.0,
            'executed_at'  => now()->subDays(10), // during league
        ]);

        // Trade AFTER league ended (should NOT count)
        TradeLog::factory()->sell()->create([
            'league_id'    => $league->id,
            'user_id'      => $viewer->id,
            'ticker'       => 'AAPL',
            'quantity'     => 5.0,
            'price'        => 180.0,
            'total_amount' => 900.0,
            'executed_at'  => now()->subDay(), // after league ended
        ]);

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/leaderboard");

        $response->assertStatus(200);

        $row = $response->json('data.leaderboard.0');
        // Only the BUY trade during the league should count
        $this->assertEquals(1, $row['total_trades']);
    }

    #[Test]
    public function post_finish_trades_do_not_affect_unique_tickers(): void
    {
        [$viewer, $token] = $this->createUserWithToken();
        $creator = User::factory()->create();

        $league = League::factory()->create([
            'status'     => LeagueStatus::Finished,
            'starts_at'  => now()->subDays(20),
            'ends_at'    => now()->subDays(2),
            'created_by' => $creator->id,
        ]);

        $this->joinLeague($viewer, $league);
        $this->createSnapshot($league, $viewer, 1100.0, 10.0, 1);

        // Trade DURING league period: AAPL
        TradeLog::factory()->buy()->create([
            'league_id'    => $league->id,
            'user_id'      => $viewer->id,
            'ticker'       => 'AAPL',
            'quantity'     => 2.0,
            'price'        => 150.0,
            'total_amount' => 300.0,
            'executed_at'  => now()->subDays(10),
        ]);

        // Post-finish sell for MSFT (new ticker — should NOT inflate unique_tickers)
        TradeLog::factory()->sell()->create([
            'league_id'    => $league->id,
            'user_id'      => $viewer->id,
            'ticker'       => 'MSFT',
            'quantity'     => 1.0,
            'price'        => 400.0,
            'total_amount' => 400.0,
            'executed_at'  => now()->subDay(),
        ]);

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/leaderboard");

        $response->assertStatus(200);

        $row = $response->json('data.leaderboard.0');
        $this->assertEquals(1, $row['unique_tickers']); // Only AAPL
    }

    #[Test]
    public function post_finish_sells_do_not_affect_win_rate(): void
    {
        [$viewer, $token] = $this->createUserWithToken();
        $creator = User::factory()->create();

        $league = League::factory()->create([
            'status'     => LeagueStatus::Finished,
            'starts_at'  => now()->subDays(20),
            'ends_at'    => now()->subDays(2),
            'created_by' => $creator->id,
        ]);

        $this->joinLeague($viewer, $league);
        $this->createSnapshot($league, $viewer, 1100.0, 10.0, 1);

        // During league: BUY AAPL at $100
        TradeLog::factory()->buy()->create([
            'league_id'    => $league->id,
            'user_id'      => $viewer->id,
            'ticker'       => 'AAPL',
            'quantity'     => 5.0,
            'price'        => 100.0,
            'total_amount' => 500.0,
            'executed_at'  => now()->subDays(15),
        ]);

        // During league: SELL AAPL at $120 (profitable, win_rate = 100%)
        TradeLog::factory()->sell()->create([
            'league_id'    => $league->id,
            'user_id'      => $viewer->id,
            'ticker'       => 'AAPL',
            'quantity'     => 3.0,
            'price'        => 120.0,
            'total_amount' => 360.0,
            'executed_at'  => now()->subDays(5),
        ]);

        // Post-finish: SELL AAPL at $80 (losing trade — should NOT lower win_rate)
        TradeLog::factory()->sell()->create([
            'league_id'    => $league->id,
            'user_id'      => $viewer->id,
            'ticker'       => 'AAPL',
            'quantity'     => 2.0,
            'price'        => 80.0,
            'total_amount' => 160.0,
            'executed_at'  => now()->subDay(), // after league ended
        ]);

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/leaderboard");

        $response->assertStatus(200);

        $row = $response->json('data.leaderboard.0');
        // Win rate should only reflect the profitable sell during the league
        $this->assertEquals(100.0, $row['win_rate']);
        // total_trades should be 2 (BUY + SELL during league)
        $this->assertEquals(2, $row['total_trades']);
    }

    #[Test]
    public function post_finish_sells_do_not_affect_best_trade(): void
    {
        [$viewer, $token] = $this->createUserWithToken();
        $creator = User::factory()->create();

        $league = League::factory()->create([
            'status'     => LeagueStatus::Finished,
            'starts_at'  => now()->subDays(20),
            'ends_at'    => now()->subDays(2),
            'created_by' => $creator->id,
        ]);

        $this->joinLeague($viewer, $league);
        $this->createSnapshot($league, $viewer, 1100.0, 10.0, 1);

        // During league: BUY AAPL at $100
        TradeLog::factory()->buy()->create([
            'league_id'    => $league->id,
            'user_id'      => $viewer->id,
            'ticker'       => 'AAPL',
            'quantity'     => 5.0,
            'price'        => 100.0,
            'total_amount' => 500.0,
            'executed_at'  => now()->subDays(15),
        ]);

        // During league: SELL AAPL at $110 → 10% return (best trade during league)
        TradeLog::factory()->sell()->create([
            'league_id'    => $league->id,
            'user_id'      => $viewer->id,
            'ticker'       => 'AAPL',
            'quantity'     => 2.0,
            'price'        => 110.0,
            'total_amount' => 220.0,
            'executed_at'  => now()->subDays(5),
        ]);

        // Post-finish: SELL AAPL at $200 → 100% return (should NOT become best_trade)
        TradeLog::factory()->sell()->create([
            'league_id'    => $league->id,
            'user_id'      => $viewer->id,
            'ticker'       => 'AAPL',
            'quantity'     => 3.0,
            'price'        => 200.0,
            'total_amount' => 600.0,
            'executed_at'  => now()->subDay(), // after league ended
        ]);

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/leaderboard");

        $response->assertStatus(200);

        $row = $response->json('data.leaderboard.0');
        $bestTrade = $row['best_trade'];
        $this->assertNotNull($bestTrade);
        $this->assertEquals('AAPL', $bestTrade['ticker']);
        // 10% return from the in-league sell, NOT 100% from the post-finish sell
        $this->assertEquals(10.0, $bestTrade['return_pct']);
    }

    #[Test]
    public function leaderboard_stats_identical_before_and_after_liquidation(): void
    {
        [$viewer, $token] = $this->createUserWithToken();
        $creator = User::factory()->create();

        $league = League::factory()->create([
            'status'     => LeagueStatus::Finished,
            'starts_at'  => now()->subDays(20),
            'ends_at'    => now()->subDays(2),
            'created_by' => $creator->id,
        ]);

        $this->joinLeague($viewer, $league);
        $this->createSnapshot($league, $viewer, 1100.0, 10.0, 1, [
            ['ticker' => 'AAPL', 'shares' => 5.0, 'value' => 900.0],
        ]);

        // During-league trades
        TradeLog::factory()->buy()->create([
            'league_id'    => $league->id,
            'user_id'      => $viewer->id,
            'ticker'       => 'AAPL',
            'quantity'     => 5.0,
            'price'        => 150.0,
            'total_amount' => 750.0,
            'executed_at'  => now()->subDays(10),
        ]);

        // Get leaderboard BEFORE any post-finish trades
        $responseBefore = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/leaderboard");

        $beforeRow = $responseBefore->json('data.leaderboard.0');

        // Now add post-finish liquidation sell trades
        TradeLog::factory()->sell()->create([
            'league_id'    => $league->id,
            'user_id'      => $viewer->id,
            'ticker'       => 'AAPL',
            'quantity'     => 5.0,
            'price'        => 180.0,
            'total_amount' => 900.0,
            'executed_at'  => now(), // after league ended
        ]);

        // Get leaderboard AFTER post-finish trades
        $responseAfter = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/leaderboard");

        $afterRow = $responseAfter->json('data.leaderboard.0');

        // All stats should be identical
        $this->assertEquals($beforeRow['total_trades'], $afterRow['total_trades']);
        $this->assertEquals($beforeRow['unique_tickers'], $afterRow['unique_tickers']);
        $this->assertEquals($beforeRow['win_rate'], $afterRow['win_rate']);
        $this->assertEquals($beforeRow['best_trade'], $afterRow['best_trade']);
        $this->assertEquals($beforeRow['return_pct'], $afterRow['return_pct']);
        $this->assertEquals($beforeRow['total_value'], $afterRow['total_value']);
        $this->assertEquals($beforeRow['rank'], $afterRow['rank']);
    }

    // -------------------------------------------------------------------------
    // Unit-level: LeaderboardService directly
    // -------------------------------------------------------------------------

    #[Test]
    public function service_query_filters_trades_by_executed_at_lte_ends_at(): void
    {
        $creator = User::factory()->create();
        $user    = User::factory()->create();

        $league = League::factory()->create([
            'status'     => LeagueStatus::Finished,
            'starts_at'  => now()->subDays(20),
            'ends_at'    => now()->subDays(2),
            'created_by' => $creator->id,
        ]);

        $this->joinLeague($user, $league);
        $this->createSnapshot($league, $user, 1100.0, 10.0, 1);

        // 3 trades during league
        for ($i = 0; $i < 3; $i++) {
            TradeLog::factory()->buy()->create([
                'league_id'    => $league->id,
                'user_id'      => $user->id,
                'ticker'       => 'AAPL',
                'quantity'     => 1.0,
                'price'        => 100.0,
                'total_amount' => 100.0,
                'executed_at'  => now()->subDays(10 + $i),
            ]);
        }

        // 2 trades after league ended (should be excluded)
        for ($i = 0; $i < 2; $i++) {
            TradeLog::factory()->sell()->create([
                'league_id'    => $league->id,
                'user_id'      => $user->id,
                'ticker'       => 'AAPL',
                'quantity'     => 1.0,
                'price'        => 120.0,
                'total_amount' => 120.0,
                'executed_at'  => now()->subDay(), // after ends_at
            ]);
        }

        /** @var LeaderboardService $service */
        $service = app(LeaderboardService::class);
        $result  = $service->getLeaderboard($league, $user);

        $row = $result['leaderboard'][0];
        $this->assertEquals(3, $row['total_trades']); // Only the 3 during-league trades
    }
}
