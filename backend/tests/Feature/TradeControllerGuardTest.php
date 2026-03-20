<?php

namespace Tests\Feature;

use App\Enums\LeagueStatus;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;
use App\Models\WallbitKey;
use App\Services\WallbitClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TradeControllerGuardTest extends TestCase
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

    private function tradePayload(string $direction = 'BUY', string $symbol = 'AAPL', float $amount = 100.0): array
    {
        return [
            'symbol'     => $symbol,
            'direction'  => $direction,
            'order_type' => 'MARKET',
            'amount'     => $amount,
        ];
    }

    // -------------------------------------------------------------------------
    // Finished league: SELL allowed
    // -------------------------------------------------------------------------

    #[Test]
    public function sell_trade_is_allowed_in_finished_league(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $league = League::factory()->finished()->create(['created_by' => $user->id]);
        $this->joinLeague($user, $league);

        $response = $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/trades", $this->tradePayload('SELL'));

        $response->assertStatus(201);
        $this->assertDatabaseHas('trades_log', [
            'league_id' => $league->id,
            'user_id'   => $user->id,
            'action'    => 'SELL',
        ]);
    }

    // -------------------------------------------------------------------------
    // Finished league: BUY blocked
    // -------------------------------------------------------------------------

    #[Test]
    public function buy_trade_is_blocked_in_finished_league(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $league = League::factory()->finished()->create(['created_by' => $user->id]);
        $this->joinLeague($user, $league);

        $response = $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/trades", $this->tradePayload('BUY'));

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Only SELL trades are allowed in finished leagues.');

        $this->assertDatabaseMissing('trades_log', [
            'league_id' => $league->id,
            'user_id'   => $user->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Active league: both BUY and SELL allowed
    // -------------------------------------------------------------------------

    #[Test]
    public function buy_trade_is_allowed_in_active_league(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $league = League::factory()->active()->create(['created_by' => $user->id]);
        $this->joinLeague($user, $league);

        $response = $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/trades", $this->tradePayload('BUY'));

        $response->assertStatus(201);
    }

    #[Test]
    public function sell_trade_is_allowed_in_active_league(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $league = League::factory()->active()->create(['created_by' => $user->id]);
        $this->joinLeague($user, $league);

        $response = $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/trades", $this->tradePayload('SELL'));

        $response->assertStatus(201);
    }

    // -------------------------------------------------------------------------
    // Upcoming league: trades blocked
    // -------------------------------------------------------------------------

    #[Test]
    public function trade_is_blocked_in_upcoming_league(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $league = League::factory()->upcoming()->create(['created_by' => $user->id]);
        $this->joinLeague($user, $league);

        $response = $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/trades", $this->tradePayload('BUY'));

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Trades can only be executed in active leagues.');
    }

    // -------------------------------------------------------------------------
    // Edge: 403 error message differs by league status
    // -------------------------------------------------------------------------

    #[Test]
    public function finished_league_buy_error_message_is_specific(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $league = League::factory()->finished()->create(['created_by' => $user->id]);
        $this->joinLeague($user, $league);

        $response = $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/trades", $this->tradePayload('BUY'));

        $response->assertStatus(403);
        $this->assertStringContainsString('SELL', $response->json('message'));
    }

    #[Test]
    public function upcoming_league_error_message_mentions_active(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $league = League::factory()->upcoming()->create(['created_by' => $user->id]);
        $this->joinLeague($user, $league);

        $response = $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/trades", $this->tradePayload('SELL'));

        $response->assertStatus(403);
        $this->assertStringContainsString('active', $response->json('message'));
    }
}
