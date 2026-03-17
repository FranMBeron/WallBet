<?php

namespace Tests\Feature;

use App\Enums\LeagueStatus;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\PortfolioSnapshot;
use App\Models\TradeLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeaderboardTest extends TestCase
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
        ?string $capturedAt = null
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
    // GET /api/leagues/{league}/leaderboard
    // -------------------------------------------------------------------------

    #[Test]
    public function leaderboard_returns_all_11_columns_with_correct_values(): void
    {
        [$viewer, $token] = $this->createUserWithToken();
        $creator = User::factory()->create();
        $league  = League::factory()->active()->create(['created_by' => $creator->id]);

        $this->joinLeague($viewer, $league, 1000.0);

        // Create snapshot with one position
        $this->createSnapshot($league, $viewer, 1100.0, 10.0, 1, [
            ['ticker' => 'AAPL', 'shares' => 1.0, 'value' => 1000.0],
        ]);

        // Buy trade for AAPL
        TradeLog::factory()->buy()->create([
            'league_id'    => $league->id,
            'user_id'      => $viewer->id,
            'ticker'       => 'AAPL',
            'quantity'     => 1.0,
            'price'        => 100.0,
            'total_amount' => 100.0,
        ]);

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/leaderboard");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertArrayHasKey('my_rank', $data);
        $this->assertArrayHasKey('leaderboard', $data);
        $this->assertCount(1, $data['leaderboard']);

        $row = $data['leaderboard'][0];
        $this->assertArrayHasKey('rank', $row);
        $this->assertArrayHasKey('rank_change', $row);
        $this->assertArrayHasKey('user', $row);
        $this->assertArrayHasKey('return_pct', $row);
        $this->assertArrayHasKey('total_value', $row);
        $this->assertArrayHasKey('pnl', $row);
        $this->assertArrayHasKey('total_trades', $row);
        $this->assertArrayHasKey('unique_tickers', $row);
        $this->assertArrayHasKey('best_trade', $row);
        $this->assertArrayHasKey('win_rate', $row);
        $this->assertArrayHasKey('risk_score', $row);

        $this->assertEquals(1, $row['rank']);
        $this->assertEquals(10.0, $row['return_pct']);
        $this->assertEquals(1100.0, $row['total_value']);
        $this->assertEquals(1, $row['total_trades']);
        $this->assertEquals(1, $row['unique_tickers']);
    }

    #[Test]
    public function my_rank_is_included_when_viewer_has_snapshot(): void
    {
        [$viewer, $token] = $this->createUserWithToken();
        $user2           = User::factory()->create();
        $creator         = User::factory()->create();
        $league          = League::factory()->active()->create(['created_by' => $creator->id]);

        $this->joinLeague($viewer, $league, 1000.0);
        $this->joinLeague($user2, $league, 1000.0);

        // user2 has better return → rank 1; viewer rank 2
        $this->createSnapshot($league, $user2, 1200.0, 20.0, 1);
        $this->createSnapshot($league, $viewer, 1100.0, 10.0, 2);

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/leaderboard");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(2, $data['my_rank']);
    }

    #[Test]
    public function my_rank_is_null_when_viewer_has_no_snapshot(): void
    {
        [$viewer, $token] = $this->createUserWithToken();
        $user2           = User::factory()->create();
        $creator         = User::factory()->create();
        $league          = League::factory()->active()->create(['created_by' => $creator->id]);

        $this->joinLeague($viewer, $league, 1000.0);
        $this->joinLeague($user2, $league, 1000.0);

        // Only user2 has a snapshot; viewer does not
        $this->createSnapshot($league, $user2, 1100.0, 10.0, 1);

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/leaderboard");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNull($data['my_rank']);
    }

    #[Test]
    public function sort_by_param_changes_row_order(): void
    {
        [$viewer, $token] = $this->createUserWithToken();
        $user2           = User::factory()->create();
        $user3           = User::factory()->create();
        $creator         = User::factory()->create();
        $league          = League::factory()->active()->create(['created_by' => $creator->id]);

        $this->joinLeague($viewer, $league, 1000.0);
        $this->joinLeague($user2, $league, 1000.0);
        $this->joinLeague($user3, $league, 1000.0);

        // viewer has most total_value but worst return_pct
        $this->createSnapshot($league, $viewer, 2000.0, 5.0, 3);
        $this->createSnapshot($league, $user2,  1500.0, 15.0, 2);
        $this->createSnapshot($league, $user3,  1200.0, 20.0, 1);

        // Default sort (return_pct DESC): user3, user2, viewer
        $defaultResponse = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/leaderboard");

        $defaultRows = $defaultResponse->json('data.leaderboard');
        $this->assertEquals($user3->id, $defaultRows[0]['user']['id']);
        $this->assertEquals($user2->id, $defaultRows[1]['user']['id']);
        $this->assertEquals($viewer->id, $defaultRows[2]['user']['id']);

        // Sort by total_value DESC: viewer, user2, user3
        $sortedResponse = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/leaderboard?sort_by=total_value");

        $sortedRows = $sortedResponse->json('data.leaderboard');
        $this->assertEquals($viewer->id, $sortedRows[0]['user']['id']);
        $this->assertEquals($user2->id, $sortedRows[1]['user']['id']);
        $this->assertEquals($user3->id, $sortedRows[2]['user']['id']);
    }

    #[Test]
    public function win_rate_and_best_trade_are_null_for_user_with_no_sells(): void
    {
        [$viewer, $token] = $this->createUserWithToken();
        $creator         = User::factory()->create();
        $league          = League::factory()->active()->create(['created_by' => $creator->id]);

        $this->joinLeague($viewer, $league, 1000.0);
        $this->createSnapshot($league, $viewer, 1100.0, 10.0, 1);

        // Only BUY trades — no SELLs
        TradeLog::factory()->buy()->create([
            'league_id'    => $league->id,
            'user_id'      => $viewer->id,
            'ticker'       => 'AAPL',
            'quantity'     => 1.0,
            'price'        => 100.0,
            'total_amount' => 100.0,
        ]);

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/leaderboard");

        $response->assertStatus(200);

        $row = $response->json('data.leaderboard.0');
        $this->assertNull($row['win_rate']);
        $this->assertNull($row['best_trade']);
    }

    #[Test]
    public function risk_score_is_low_for_user_with_empty_positions(): void
    {
        [$viewer, $token] = $this->createUserWithToken();
        $creator         = User::factory()->create();
        $league          = League::factory()->active()->create(['created_by' => $creator->id]);

        $this->joinLeague($viewer, $league, 1000.0);
        // Snapshot with empty positions array
        $this->createSnapshot($league, $viewer, 1000.0, 0.0, 1, []);

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/leaderboard");

        $response->assertStatus(200);

        $row = $response->json('data.leaderboard.0');
        $this->assertEquals('Low', $row['risk_score']);
    }

    #[Test]
    public function risk_score_is_high_for_concentrated_portfolio(): void
    {
        [$viewer, $token] = $this->createUserWithToken();
        $creator         = User::factory()->create();
        $league          = League::factory()->active()->create(['created_by' => $creator->id]);

        $this->joinLeague($viewer, $league, 1000.0);
        // One position = 80% of total value (>60% = High)
        $this->createSnapshot($league, $viewer, 1000.0, 0.0, 1, [
            ['ticker' => 'AAPL', 'shares' => 1.0, 'value' => 800.0],
            ['ticker' => 'MSFT', 'shares' => 1.0, 'value' => 200.0],
        ]);

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/leaderboard");

        $response->assertStatus(200);

        $row = $response->json('data.leaderboard.0');
        $this->assertEquals('High', $row['risk_score']);
    }

    #[Test]
    public function rank_change_is_zero_when_user_has_only_one_snapshot(): void
    {
        [$viewer, $token] = $this->createUserWithToken();
        $creator         = User::factory()->create();
        $league          = League::factory()->active()->create(['created_by' => $creator->id]);

        $this->joinLeague($viewer, $league, 1000.0);
        // Single snapshot — no previous to compare against
        $this->createSnapshot($league, $viewer, 1100.0, 10.0, 1);

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/leaderboard");

        $response->assertStatus(200);

        $row = $response->json('data.leaderboard.0');
        $this->assertEquals(0, $row['rank_change']);
    }

    // -------------------------------------------------------------------------
    // GET /api/leagues/{league}/leaderboard/history
    // -------------------------------------------------------------------------

    #[Test]
    public function history_returns_aligned_dates_with_null_for_late_joining_participant(): void
    {
        [$viewer, $token] = $this->createUserWithToken();
        $userB           = User::factory()->create();
        $creator         = User::factory()->create();
        $league          = League::factory()->active()->create(['created_by' => $creator->id]);

        $this->joinLeague($viewer, $league, 1000.0);
        $this->joinLeague($userB, $league, 1000.0);

        $day1 = now()->subDays(2)->startOfDay();
        $day2 = now()->subDay()->startOfDay();

        // User A (viewer) has snapshots on days 1 and 2
        $this->createSnapshot($league, $viewer, 1050.0, 5.0, 1, [], $day1->toDateTimeString());
        $this->createSnapshot($league, $viewer, 1100.0, 10.0, 1, [], $day2->toDateTimeString());

        // User B only has a snapshot on day 2
        $this->createSnapshot($league, $userB, 1080.0, 8.0, 2, [], $day2->toDateTimeString());

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/leaderboard/history");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data['dates']);

        // Find user A and user B in participants
        $participants = $data['participants'];
        $pA = collect($participants)->firstWhere('user.id', $viewer->id);
        $pB = collect($participants)->firstWhere('user.id', $userB->id);

        $this->assertNotNull($pA);
        $this->assertNotNull($pB);

        // User A: both days present
        $this->assertNotNull($pA['ranks'][0]);
        $this->assertNotNull($pA['ranks'][1]);

        // User B: first day null, second day present
        $this->assertNull($pB['ranks'][0]);
        $this->assertNotNull($pB['ranks'][1]);
    }

    #[Test]
    public function history_returns_empty_dates_and_participants_when_no_snapshots_exist(): void
    {
        [$viewer, $token] = $this->createUserWithToken();
        $creator         = User::factory()->create();
        $league          = League::factory()->active()->create(['created_by' => $creator->id]);

        $this->joinLeague($viewer, $league, 1000.0);
        // No snapshots created

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/leaderboard/history");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEmpty($data['dates']);
        $this->assertEmpty($data['participants']);
    }
}
