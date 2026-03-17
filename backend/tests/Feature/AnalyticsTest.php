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

class AnalyticsTest extends TestCase
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
    // GET /api/leagues/{league}/analytics
    // -------------------------------------------------------------------------

    #[Test]
    public function analytics_returns_base_stats_and_omits_top_tickers_for_active_league(): void
    {
        [$viewer, $token] = $this->createUserWithToken();
        $creator = User::factory()->create();
        $league  = League::factory()->active()->create(['created_by' => $creator->id]);

        $this->joinLeague($viewer, $league, 1000.0);
        $this->createSnapshot($league, $viewer, 1100.0, 10.0, 1, [
            ['ticker' => 'AAPL', 'shares' => 1.0, 'value' => 1000.0],
        ]);

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/analytics");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertArrayHasKey('avg_return_pct', $data);
        $this->assertArrayHasKey('median_return_pct', $data);
        $this->assertArrayHasKey('positive_count', $data);
        $this->assertArrayHasKey('negative_count', $data);
        $this->assertArrayHasKey('returns_distribution', $data);
        $this->assertArrayHasKey('avg_diversification', $data);
        $this->assertArrayHasKey('total_trades', $data);
        $this->assertArrayHasKey('trades_per_day', $data);

        // top_tickers must be absent for active leagues
        $this->assertArrayNotHasKey('top_tickers', $data);
    }

    #[Test]
    public function analytics_includes_top_tickers_for_finished_league(): void
    {
        [$viewer, $token] = $this->createUserWithToken();
        $user2   = User::factory()->create();
        $creator = User::factory()->create();
        $league  = League::factory()->finished()->create(['created_by' => $creator->id]);

        $this->joinLeague($viewer, $league, 1000.0);
        $this->joinLeague($user2, $league, 1000.0);

        $this->createSnapshot($league, $viewer, 1100.0, 10.0, 1, [
            ['ticker' => 'AAPL', 'shares' => 1.0, 'value' => 900.0],
        ]);
        $this->createSnapshot($league, $user2, 1050.0, 5.0, 2, [
            ['ticker' => 'AAPL', 'shares' => 0.5, 'value' => 450.0],
            ['ticker' => 'MSFT', 'shares' => 1.0, 'value' => 500.0],
        ]);

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/analytics");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertArrayHasKey('top_tickers', $data);
        $this->assertIsArray($data['top_tickers']);
        $this->assertNotEmpty($data['top_tickers']);

        // AAPL should appear since both users hold it
        $tickers = array_column($data['top_tickers'], 'ticker');
        $this->assertContains('AAPL', $tickers);

        // Each ticker entry has required keys
        $this->assertArrayHasKey('ticker', $data['top_tickers'][0]);
        $this->assertArrayHasKey('holders', $data['top_tickers'][0]);
        $this->assertArrayHasKey('avg_weight', $data['top_tickers'][0]);
    }

    #[Test]
    public function returns_distribution_bucket_counts_sum_to_participant_count(): void
    {
        [$viewer, $token] = $this->createUserWithToken();
        $users   = User::factory()->count(4)->create();
        $creator = User::factory()->create();
        $league  = League::factory()->finished()->create(['created_by' => $creator->id]);

        $this->joinLeague($viewer, $league, 1000.0);
        foreach ($users as $u) {
            $this->joinLeague($u, $league, 1000.0);
        }

        // Viewer + 4 users = 5 participants, spread across buckets
        $this->createSnapshot($league, $viewer,     1000.0,  0.0,  1); // 0 to 5 bucket
        $this->createSnapshot($league, $users[0],   1100.0, 10.0,  2); // 10 to 20 bucket
        $this->createSnapshot($league, $users[1],    900.0, -10.0, 3); // -10 to -5 bucket
        $this->createSnapshot($league, $users[2],   1250.0, 25.0,  4); // >20 bucket
        $this->createSnapshot($league, $users[3],    980.0, -2.0,  5); // -5 to 0 bucket

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/analytics");

        $response->assertStatus(200);

        $distribution = $response->json('data.returns_distribution');
        $this->assertIsArray($distribution);

        $totalCount = array_sum(array_column($distribution, 'count'));
        $this->assertEquals(5, $totalCount);
    }

    #[Test]
    public function analytics_returns_safe_defaults_when_no_snapshots_exist(): void
    {
        [$viewer, $token] = $this->createUserWithToken();
        $creator = User::factory()->create();
        $league  = League::factory()->active()->create(['created_by' => $creator->id]);

        $this->joinLeague($viewer, $league, 1000.0);
        // No snapshots created

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/analytics");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNull($data['avg_return_pct']);
        $this->assertNull($data['median_return_pct']);
        $this->assertEquals(0, $data['positive_count']);
        $this->assertEquals(0, $data['negative_count']);
        $this->assertEmpty($data['returns_distribution']);
        $this->assertEquals(0, $data['total_trades']);
        $this->assertNull($data['avg_diversification']);
    }

    // -------------------------------------------------------------------------
    // GET /api/leagues/{league}/compare
    // -------------------------------------------------------------------------

    #[Test]
    public function compare_returns_403_when_league_is_active(): void
    {
        [$viewer, $token] = $this->createUserWithToken();
        $user1   = User::factory()->create();
        $user2   = User::factory()->create();
        $creator = User::factory()->create();
        $league  = League::factory()->active()->create(['created_by' => $creator->id]);

        $this->joinLeague($viewer, $league, 1000.0);
        $this->joinLeague($user1, $league, 1000.0);
        $this->joinLeague($user2, $league, 1000.0);

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/compare?user1={$user1->id}&user2={$user2->id}");

        $response->assertStatus(403);

        $body = $response->json();
        $this->assertArrayHasKey('message', $body);
        $this->assertArrayHasKey('ends_at', $body);
    }

    #[Test]
    public function compare_returns_422_when_user1_is_not_a_league_member(): void
    {
        [$viewer, $token] = $this->createUserWithToken();
        $nonMember = User::factory()->create();
        $user2     = User::factory()->create();
        $creator   = User::factory()->create();
        $league    = League::factory()->finished()->create(['created_by' => $creator->id]);

        $this->joinLeague($viewer, $league, 1000.0);
        $this->joinLeague($user2, $league, 1000.0);
        // nonMember is NOT joined

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/compare?user1={$nonMember->id}&user2={$user2->id}");

        $response->assertStatus(422);
    }

    #[Test]
    public function compare_returns_422_when_user2_is_not_a_league_member(): void
    {
        [$viewer, $token] = $this->createUserWithToken();
        $user1     = User::factory()->create();
        $nonMember = User::factory()->create();
        $creator   = User::factory()->create();
        $league    = League::factory()->finished()->create(['created_by' => $creator->id]);

        $this->joinLeague($viewer, $league, 1000.0);
        $this->joinLeague($user1, $league, 1000.0);
        // nonMember is NOT joined

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/compare?user1={$user1->id}&user2={$nonMember->id}");

        $response->assertStatus(422);
    }

    #[Test]
    public function compare_returns_200_with_correct_shape_for_finished_league(): void
    {
        [$viewer, $token] = $this->createUserWithToken();
        $user1   = User::factory()->create();
        $user2   = User::factory()->create();
        $creator = User::factory()->create();
        $league  = League::factory()->finished()->create(['created_by' => $creator->id]);

        $this->joinLeague($viewer, $league, 1000.0);
        $this->joinLeague($user1, $league, 1000.0);
        $this->joinLeague($user2, $league, 1000.0);

        $day1 = now()->subDays(2)->startOfDay();
        $day2 = now()->subDay()->startOfDay();

        // Snapshots with overlapping tickers
        $this->createSnapshot($league, $user1, 1100.0, 10.0, 1, [
            ['ticker' => 'AAPL', 'shares' => 1.0, 'value' => 900.0],
            ['ticker' => 'MSFT', 'shares' => 2.0, 'value' => 200.0],
        ], $day2->toDateTimeString());

        $this->createSnapshot($league, $user2, 1050.0, 5.0, 2, [
            ['ticker' => 'AAPL', 'shares' => 0.5, 'value' => 450.0],
            ['ticker' => 'TSLA', 'shares' => 1.0, 'value' => 500.0],
        ], $day2->toDateTimeString());

        // Historical snapshots
        $this->createSnapshot($league, $user1, 1050.0, 5.0, 1, [], $day1->toDateTimeString());
        $this->createSnapshot($league, $user2, 1030.0, 3.0, 2, [], $day1->toDateTimeString());

        // BUY trades
        TradeLog::factory()->buy()->create([
            'league_id'    => $league->id,
            'user_id'      => $user1->id,
            'ticker'       => 'AAPL',
            'quantity'     => 1.0,
            'price'        => 100.0,
            'total_amount' => 100.0,
        ]);

        TradeLog::factory()->buy()->create([
            'league_id'    => $league->id,
            'user_id'      => $user2->id,
            'ticker'       => 'AAPL',
            'quantity'     => 0.5,
            'price'        => 100.0,
            'total_amount' => 50.0,
        ]);

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/compare?user1={$user1->id}&user2={$user2->id}");

        $response->assertStatus(200);

        $data = $response->json('data');

        // Top-level keys
        $this->assertArrayHasKey('user1', $data);
        $this->assertArrayHasKey('user2', $data);
        $this->assertArrayHasKey('shared_tickers', $data);
        $this->assertArrayHasKey('evolution', $data);

        // User blocks
        foreach (['user1', 'user2'] as $key) {
            $this->assertArrayHasKey('id', $data[$key]);
            $this->assertArrayHasKey('return_pct', $data[$key]);
            $this->assertArrayHasKey('total_trades', $data[$key]);
            $this->assertArrayHasKey('unique_tickers', $data[$key]);
            $this->assertArrayHasKey('win_rate', $data[$key]);
            $this->assertArrayHasKey('positions', $data[$key]);
        }

        // AAPL is shared between user1 and user2
        $this->assertContains('AAPL', $data['shared_tickers']);
        // MSFT is only user1's, TSLA only user2's
        $this->assertNotContains('MSFT', $data['shared_tickers']);
        $this->assertNotContains('TSLA', $data['shared_tickers']);

        // Evolution shape
        $evolution = $data['evolution'];
        $this->assertArrayHasKey('dates', $evolution);
        $this->assertArrayHasKey('user1_returns', $evolution);
        $this->assertArrayHasKey('user2_returns', $evolution);
        $this->assertCount(2, $evolution['dates']);
        $this->assertCount(2, $evolution['user1_returns']);
        $this->assertCount(2, $evolution['user2_returns']);
    }
}
