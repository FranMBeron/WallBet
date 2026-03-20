<?php

namespace Tests\Feature;

use App\Enums\LeagueStatus;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\PortfolioSnapshot;
use App\Models\User;
use App\Services\PortfolioService;
use App\Services\WallbitClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LiquidationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['wallbet.encryption_key' => bin2hex(random_bytes(32))]);
        config(['app.demo_mode' => true]);
    }

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
        array $positions = [],
        float $totalValue = 1000.0,
        ?string $capturedAt = null,
    ): PortfolioSnapshot {
        return PortfolioSnapshot::create([
            'league_id'      => $league->id,
            'user_id'        => $user->id,
            'total_value'    => $totalValue,
            'cash_available' => 100.0,
            'positions'      => $positions,
            'rank'           => 1,
            'return_pct'     => 0.0,
            'captured_at'    => $capturedAt ?? now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Finished league with positions: sells execute
    // -------------------------------------------------------------------------

    #[Test]
    public function liquidate_sells_all_positions_in_finished_league(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $league = League::factory()->finished()->create(['created_by' => $user->id]);
        $this->joinLeague($user, $league);

        // Snapshot with 2 positions that have shares > 0
        $this->createSnapshot($league, $user, [
            ['ticker' => 'AAPL', 'shares' => 5.0, 'avg_cost' => 150.0, 'current_price' => 185.0, 'current_value' => 925.0],
            ['ticker' => 'MSFT', 'shares' => 2.0, 'avg_cost' => 400.0, 'current_price' => 420.0, 'current_value' => 840.0],
        ], 1865.0);

        $response = $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/liquidate");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data['results']);
        $this->assertEquals(2, $data['total_sold']);
        $this->assertEquals(0, $data['total_failed']);

        // Verify each result has expected structure
        foreach ($data['results'] as $result) {
            $this->assertArrayHasKey('ticker', $result);
            $this->assertArrayHasKey('status', $result);
            $this->assertEquals('ok', $result['status']);
            $this->assertArrayHasKey('shares', $result);
            $this->assertArrayHasKey('amount', $result);
        }

        // Verify trade logs were created
        $this->assertDatabaseHas('trades_log', [
            'league_id' => $league->id,
            'user_id'   => $user->id,
            'ticker'    => 'AAPL',
            'action'    => 'SELL',
        ]);
        $this->assertDatabaseHas('trades_log', [
            'league_id' => $league->id,
            'user_id'   => $user->id,
            'ticker'    => 'MSFT',
            'action'    => 'SELL',
        ]);
    }

    // -------------------------------------------------------------------------
    // Finished league with 0 positions: empty result, no error
    // -------------------------------------------------------------------------

    #[Test]
    public function liquidate_returns_empty_results_when_no_positions(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $league = League::factory()->finished()->create(['created_by' => $user->id]);
        $this->joinLeague($user, $league);

        // Snapshot with no positions
        $this->createSnapshot($league, $user, [], 1000.0);

        $response = $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/liquidate");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEmpty($data['results']);
        $this->assertEquals(0, $data['total_sold']);
        $this->assertEquals(0, $data['total_failed']);
    }

    #[Test]
    public function liquidate_skips_positions_with_zero_shares(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $league = League::factory()->finished()->create(['created_by' => $user->id]);
        $this->joinLeague($user, $league);

        // Snapshot with one position that has 0 shares (already sold)
        $this->createSnapshot($league, $user, [
            ['ticker' => 'AAPL', 'shares' => 0.0, 'avg_cost' => 150.0, 'current_price' => 185.0, 'current_value' => 0.0],
            ['ticker' => 'MSFT', 'shares' => 3.0, 'avg_cost' => 400.0, 'current_price' => 420.0, 'current_value' => 1260.0],
        ], 1260.0);

        $response = $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/liquidate");

        $response->assertStatus(200);

        $data = $response->json('data');
        // Only MSFT should be sold (AAPL has 0 shares)
        $this->assertCount(1, $data['results']);
        $this->assertEquals(1, $data['total_sold']);
        $this->assertEquals('MSFT', $data['results'][0]['ticker']);
    }

    // -------------------------------------------------------------------------
    // Active league: 403 forbidden
    // -------------------------------------------------------------------------

    #[Test]
    public function liquidate_returns_403_for_active_league(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $league = League::factory()->active()->create(['created_by' => $user->id]);
        $this->joinLeague($user, $league);

        $response = $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/liquidate");

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Liquidation is only allowed in finished leagues.');
    }

    #[Test]
    public function liquidate_returns_403_for_upcoming_league(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $league = League::factory()->upcoming()->create(['created_by' => $user->id]);
        $this->joinLeague($user, $league);

        $response = $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/liquidate");

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Liquidation is only allowed in finished leagues.');
    }

    // -------------------------------------------------------------------------
    // Partial failure: some positions succeed, some fail
    // -------------------------------------------------------------------------

    #[Test]
    public function liquidate_handles_partial_failure_without_short_circuit(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $league = League::factory()->finished()->create(['created_by' => $user->id]);
        $this->joinLeague($user, $league);

        // Create snapshot with 3 positions
        $this->createSnapshot($league, $user, [
            ['ticker' => 'AAPL', 'shares' => 2.0, 'avg_cost' => 150.0, 'current_price' => 185.0, 'current_value' => 370.0],
            ['ticker' => 'INVALID_TICKER', 'shares' => 1.0, 'avg_cost' => 100.0, 'current_price' => 100.0, 'current_value' => 100.0],
            ['ticker' => 'MSFT', 'shares' => 3.0, 'avg_cost' => 400.0, 'current_price' => 420.0, 'current_value' => 1260.0],
        ], 1730.0);

        // Mock the WallbitClient to fail on the second position
        $mockClient = $this->createMock(WallbitClient::class);
        $callCount  = 0;
        $mockClient->method('executeTrade')
            ->willReturnCallback(function ($apiKey, $symbol, $direction, $orderType, $amount) use (&$callCount) {
                $callCount++;
                if ($symbol === 'INVALID_TICKER') {
                    throw new \RuntimeException('Asset not found');
                }
                return [
                    'symbol'     => $symbol,
                    'direction'  => $direction,
                    'shares'     => $amount / 185.0,
                    'amount'     => $amount,
                    'status'     => 'FILLED',
                    'created_at' => now()->toIso8601String(),
                ];
            });

        $this->app->instance(WallbitClient::class, $mockClient);

        $response = $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/liquidate");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(3, $data['results']);
        $this->assertEquals(2, $data['total_sold']);
        $this->assertEquals(1, $data['total_failed']);

        // Verify the failed position is reported
        $failedResults = collect($data['results'])->where('status', 'failed');
        $this->assertCount(1, $failedResults);
        $this->assertEquals('INVALID_TICKER', $failedResults->first()['ticker']);

        // Verify the successful positions still created trade logs
        $this->assertDatabaseHas('trades_log', [
            'league_id' => $league->id,
            'ticker'    => 'AAPL',
            'action'    => 'SELL',
        ]);
        $this->assertDatabaseHas('trades_log', [
            'league_id' => $league->id,
            'ticker'    => 'MSFT',
            'action'    => 'SELL',
        ]);
        // Failed position should NOT have a trade log
        $this->assertDatabaseMissing('trades_log', [
            'league_id' => $league->id,
            'ticker'    => 'INVALID_TICKER',
        ]);
    }

    // -------------------------------------------------------------------------
    // Non-member: 403 from middleware
    // -------------------------------------------------------------------------

    #[Test]
    public function liquidate_returns_403_for_non_member(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $creator = User::factory()->create();
        $league  = League::factory()->finished()->create(['created_by' => $creator->id]);
        // user is NOT a member

        $response = $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/liquidate");

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Response structure validation
    // -------------------------------------------------------------------------

    #[Test]
    public function liquidate_response_has_correct_structure(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $league = League::factory()->finished()->create(['created_by' => $user->id]);
        $this->joinLeague($user, $league);

        $this->createSnapshot($league, $user, [
            ['ticker' => 'AAPL', 'shares' => 1.0, 'avg_cost' => 150.0, 'current_price' => 185.0, 'current_value' => 185.0],
        ], 1185.0);

        $response = $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/liquidate");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'results' => [
                        '*' => ['ticker', 'status', 'shares', 'amount'],
                    ],
                    'total_sold',
                    'total_failed',
                ],
            ]);
    }
}
