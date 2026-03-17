<?php

namespace Tests\Feature;

use App\Enums\LeagueStatus;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\TradeLog;
use App\Models\User;
use App\Models\WallbitKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortfolioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['wallbet.encryption_key' => bin2hex(random_bytes(32))]);
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

    private function connectWallbit(User $user): WallbitKey
    {
        Http::fake([
            '*/balance/checking' => Http::response([
                'data' => [['currency' => 'USD', 'balance' => 1000.0]],
            ], 200),
        ]);

        $token = $user->createToken('vault')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/wallbit/connect', ['api_key' => 'valid-key-123']);

        return WallbitKey::where('user_id', $user->id)->first();
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

    private function fakeSuccessfulTrade(float $shares = 0.5): void
    {
        Http::fake([
            '*/trades' => Http::response([
                'data' => [
                    'symbol'     => 'AAPL',
                    'direction'  => 'BUY',
                    'shares'     => $shares,
                    'amount'     => 100.0,
                    'status'     => 'executed',
                    'created_at' => now()->toIso8601String(),
                ],
            ], 200),
        ]);
    }

    private function fakeFailedTrade(): void
    {
        Http::fake([
            '*/trades' => Http::response(['message' => 'Insufficient balance'], 422),
        ]);
    }

    private function fakeAssetPrice(string $symbol = 'AAPL', float $price = 220.0): void
    {
        Http::fake([
            "*/assets/{$symbol}" => Http::response([
                'data' => [
                    'symbol' => $symbol,
                    'price'  => $price,
                    'name'   => "{$symbol} Inc.",
                    'sector' => 'Technology',
                ],
            ], 200),
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/leagues/{league}/trades
    // -------------------------------------------------------------------------

    #[Test]
    public function post_trade_success_returns_201_and_inserts_trades_log_row(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $this->connectWallbit($user);

        $other  = User::factory()->create();
        $league = League::factory()->active()->create(['created_by' => $other->id]);
        $this->joinLeague($user, $league);

        $this->fakeSuccessfulTrade(0.5);

        $response = $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/trades", [
                'symbol'     => 'AAPL',
                'direction'  => 'BUY',
                'order_type' => 'MARKET',
                'amount'     => 100.0,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.ticker', 'AAPL')
            ->assertJsonPath('data.action', 'BUY');

        $this->assertDatabaseHas('trades_log', [
            'league_id' => $league->id,
            'user_id'   => $user->id,
            'ticker'    => 'AAPL',
            'action'    => 'BUY',
        ]);
    }

    #[Test]
    public function post_trade_wallbit_failure_returns_422_and_no_trades_log_row(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $this->connectWallbit($user);

        $other  = User::factory()->create();
        $league = League::factory()->active()->create(['created_by' => $other->id]);
        $this->joinLeague($user, $league);

        $this->fakeFailedTrade();

        $response = $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/trades", [
                'symbol'     => 'AAPL',
                'direction'  => 'BUY',
                'order_type' => 'MARKET',
                'amount'     => 100.0,
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseCount('trades_log', 0);
    }

    #[Test]
    public function post_trade_validation_failure_returns_422_without_touching_wallbit_or_db(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $this->connectWallbit($user);

        $other  = User::factory()->create();
        $league = League::factory()->active()->create(['created_by' => $other->id]);
        $this->joinLeague($user, $league);

        Http::fake(); // no WallBit calls should happen

        $response = $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/trades", [
                // missing 'symbol'
                'direction'  => 'BUY',
                'order_type' => 'MARKET',
                'amount'     => 100.0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['symbol']);

        $this->assertDatabaseCount('trades_log', 0);
        Http::assertNothingSent();
    }

    #[Test]
    public function post_trade_non_member_returns_403(): void
    {
        [$user, $token] = $this->createUserWithToken();

        $other  = User::factory()->create();
        $league = League::factory()->active()->create(['created_by' => $other->id]);
        // $user is NOT a member

        $response = $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/trades", [
                'symbol'     => 'AAPL',
                'direction'  => 'BUY',
                'order_type' => 'MARKET',
                'amount'     => 100.0,
            ]);

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // GET /api/leagues/{league}/trades
    // -------------------------------------------------------------------------

    #[Test]
    public function get_trades_returns_only_authenticated_users_rows(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $other = User::factory()->create();

        $creator = User::factory()->create();
        $league  = League::factory()->active()->create(['created_by' => $creator->id]);
        $this->joinLeague($user, $league);
        $this->joinLeague($other, $league);

        // 3 trades for the authenticated user
        TradeLog::factory()->count(3)->create([
            'league_id' => $league->id,
            'user_id'   => $user->id,
        ]);

        // 2 trades for the other user — must NOT appear
        TradeLog::factory()->count(2)->create([
            'league_id' => $league->id,
            'user_id'   => $other->id,
        ]);

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/trades");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    // -------------------------------------------------------------------------
    // GET /api/leagues/{league}/portfolio
    // -------------------------------------------------------------------------

    #[Test]
    public function get_portfolio_own_returns_200_with_correct_positions_and_totals(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $this->connectWallbit($user);

        $other  = User::factory()->create();
        $league = League::factory()->active()->create(['created_by' => $other->id]);
        $this->joinLeague($user, $league, 1000.0);

        TradeLog::factory()->buy()->create([
            'league_id'    => $league->id,
            'user_id'      => $user->id,
            'ticker'       => 'AAPL',
            'quantity'     => 1.0,
            'price'        => 200.0,
            'total_amount' => 200.0,
        ]);

        $this->fakeAssetPrice('AAPL', 220.0);

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}/portfolio");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertArrayHasKey('positions', $data);
        $this->assertCount(1, $data['positions']);
        $this->assertEquals('AAPL', $data['positions'][0]['ticker']);
        $this->assertEquals(800.0, $data['cash_available']);    // 1000 - 200
        $this->assertEquals(1020.0, $data['total_value']);      // 1*220 + 800
        $this->assertEquals(2.0, $data['return_pct']);          // (1020-1000)/1000*100
    }

    #[Test]
    public function get_portfolio_other_member_in_active_league_returns_403(): void
    {
        [$viewer, $viewerToken] = $this->createUserWithToken();
        $target = User::factory()->create();

        $creator = User::factory()->create();
        $league  = League::factory()->active()->create(['created_by' => $creator->id]);
        $this->joinLeague($viewer, $league);
        $this->joinLeague($target, $league);

        $response = $this->withToken($viewerToken)
            ->getJson("/api/leagues/{$league->id}/portfolio?user_id={$target->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function get_portfolio_other_member_in_finished_league_returns_200(): void
    {
        [$viewer, $viewerToken] = $this->createUserWithToken();
        $target = User::factory()->create();
        $this->connectWallbit($target);

        $creator = User::factory()->create();
        $league  = League::factory()->finished()->create(['created_by' => $creator->id]);
        $this->joinLeague($viewer, $league);
        $this->joinLeague($target, $league, 1000.0);

        Http::fake([
            '*/assets/*' => Http::response([
                'data' => ['symbol' => 'AAPL', 'price' => 220.0, 'name' => 'Apple', 'sector' => 'Technology'],
            ], 200),
        ]);

        $response = $this->withToken($viewerToken)
            ->getJson("/api/leagues/{$league->id}/portfolio?user_id={$target->id}");

        $response->assertStatus(200);
    }
}
