<?php

namespace Tests\Feature;

use App\Enums\LeagueStatus;
use App\Enums\LeagueType;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;
use App\Models\WallbitKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LeagueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set a valid encryption key for vault operations
        config(['wallbet.encryption_key' => bin2hex(random_bytes(32))]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function fakeWallbitBalance(float $balance = 1000.0): void
    {
        Http::fake([
            '*/balance/checking' => Http::response([
                'data' => [['currency' => 'USD', 'balance' => $balance]],
            ], 200),
        ]);
    }

    private function createUserWithToken(): array
    {
        $user  = User::factory()->create();
        $token = $user->createToken('auth')->plainTextToken;
        return [$user, $token];
    }

    private function connectWallbit(User $user): WallbitKey
    {
        $this->fakeWallbitBalance();
        $token = $user->createToken('vault')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/wallbit/connect', ['api_key' => 'valid-key-123']);

        return WallbitKey::where('user_id', $user->id)->first();
    }

    // -------------------------------------------------------------------------
    // GET /api/leagues
    // -------------------------------------------------------------------------

    /** @test */
    public function index_returns_only_public_leagues(): void
    {
        [$user, $token] = $this->createUserWithToken();

        League::factory()->count(3)->public()->upcoming()->create(['created_by' => $user->id]);
        League::factory()->count(2)->private()->upcoming()->create(['created_by' => $user->id]);

        $response = $this->withToken($token)
            ->getJson('/api/leagues');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function index_requires_authentication(): void
    {
        $this->getJson('/api/leagues')
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // GET /api/leagues/my
    // -------------------------------------------------------------------------

    /** @test */
    public function my_returns_leagues_where_user_is_member(): void
    {
        [$user, $token] = $this->createUserWithToken();

        $public  = League::factory()->public()->upcoming()->create(['created_by' => $user->id]);
        $private = League::factory()->private()->upcoming()->create(['created_by' => $user->id]);
        // A league the user is NOT a member of
        League::factory()->public()->upcoming()->create(['created_by' => $user->id]);

        LeagueMember::create(['league_id' => $public->id,  'user_id' => $user->id, 'initial_capital' => 100, 'joined_at' => now()]);
        LeagueMember::create(['league_id' => $private->id, 'user_id' => $user->id, 'initial_capital' => 100, 'joined_at' => now()]);

        $response = $this->withToken($token)
            ->getJson('/api/leagues/my');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function my_returns_empty_when_no_memberships(): void
    {
        [$user, $token] = $this->createUserWithToken();

        $response = $this->withToken($token)
            ->getJson('/api/leagues/my');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    // -------------------------------------------------------------------------
    // GET /api/leagues/invite/{code}
    // -------------------------------------------------------------------------

    /** @test */
    public function find_by_code_returns_league_without_password_field(): void
    {
        [$user, $token] = $this->createUserWithToken();

        $league = League::factory()->private()->upcoming()->create([
            'invite_code' => 'ABC12345',
            'created_by'  => $user->id,
        ]);

        $response = $this->withToken($token)
            ->getJson('/api/leagues/invite/ABC12345');

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('password', $response->json('data'));
    }

    /** @test */
    public function find_by_code_returns_404_for_unknown_code(): void
    {
        [$user, $token] = $this->createUserWithToken();

        $this->withToken($token)
            ->getJson('/api/leagues/invite/XXXXXXXX')
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // GET /api/leagues/{id}
    // -------------------------------------------------------------------------

    /** @test */
    public function show_returns_is_member_true_for_member(): void
    {
        [$user, $token] = $this->createUserWithToken();

        $league = League::factory()->public()->upcoming()->create(['created_by' => $user->id]);
        LeagueMember::create(['league_id' => $league->id, 'user_id' => $user->id, 'initial_capital' => 100, 'joined_at' => now()]);

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_member', true);
    }

    /** @test */
    public function show_returns_is_member_false_for_non_member(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $other = User::factory()->create();

        $league = League::factory()->public()->upcoming()->create(['created_by' => $other->id]);

        $response = $this->withToken($token)
            ->getJson("/api/leagues/{$league->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_member', false);
    }

    /** @test */
    public function show_returns_404_for_nonexistent_league(): void
    {
        [$user, $token] = $this->createUserWithToken();

        $this->withToken($token)
            ->getJson('/api/leagues/00000000-0000-0000-0000-000000000000')
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // POST /api/leagues
    // -------------------------------------------------------------------------

    /** @test */
    public function store_creates_public_league_with_invite_code_and_upcoming_status(): void
    {
        [$user, $token] = $this->createUserWithToken();

        $response = $this->withToken($token)->postJson('/api/leagues', [
            'name'             => 'Test League',
            'type'             => LeagueType::Sponsored->value,
            'buy_in'           => 100,
            'max_participants' => 10,
            'starts_at'        => now()->addDay()->toIso8601String(),
            'ends_at'          => now()->addDays(30)->toIso8601String(),
            'is_public'        => true,
        ]);

        $response->assertStatus(201);
        $data = $response->json('data');

        $this->assertEquals('upcoming', $data['status']->value ?? $data['status']);
        $this->assertNotNull($data['invite_code']);
        $this->assertEquals(8, strlen($data['invite_code']));
        $this->assertNull($data['password'] ?? null);

        $this->assertDatabaseHas('leagues', [
            'name'   => 'Test League',
            'status' => 'upcoming',
        ]);
    }

    /** @test */
    public function store_creates_private_league_with_bcrypt_password(): void
    {
        [$user, $token] = $this->createUserWithToken();

        $response = $this->withToken($token)->postJson('/api/leagues', [
            'name'             => 'Private League',
            'type'             => LeagueType::Private->value,
            'buy_in'           => 100,
            'max_participants' => 5,
            'starts_at'        => now()->addDay()->toIso8601String(),
            'ends_at'          => now()->addDays(30)->toIso8601String(),
            'is_public'        => false,
            'password'         => 'supersecret',
        ]);

        $response->assertStatus(201);

        $league = League::where('name', 'Private League')->first();
        $this->assertNotNull($league->password);
        $this->assertTrue(Hash::check('supersecret', $league->password));
        $this->assertArrayNotHasKey('password', $response->json('data'));
    }

    /** @test */
    public function store_returns_422_when_starts_at_is_missing(): void
    {
        [$user, $token] = $this->createUserWithToken();

        $this->withToken($token)->postJson('/api/leagues', [
            'name'             => 'No Date League',
            'type'             => LeagueType::Sponsored->value,
            'buy_in'           => 100,
            'max_participants' => 5,
            'ends_at'          => now()->addDays(30)->toIso8601String(),
            'is_public'        => true,
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['starts_at']);
    }

    /** @test */
    public function store_returns_422_when_ends_at_is_before_starts_at(): void
    {
        [$user, $token] = $this->createUserWithToken();

        $this->withToken($token)->postJson('/api/leagues', [
            'name'             => 'Bad Dates League',
            'type'             => LeagueType::Sponsored->value,
            'buy_in'           => 100,
            'max_participants' => 5,
            'starts_at'        => now()->addDays(10)->toIso8601String(),
            'ends_at'          => now()->addDays(5)->toIso8601String(),
            'is_public'        => true,
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['ends_at']);
    }

    // -------------------------------------------------------------------------
    // POST /api/leagues/{id}/join
    // -------------------------------------------------------------------------

    /** @test */
    public function join_public_league_successfully(): void
    {
        $this->fakeWallbitBalance(500.0);

        [$user, $token] = $this->createUserWithToken();
        $this->connectWallbit($user);
        $this->fakeWallbitBalance(500.0); // re-fake after connectWallbit call

        $other  = User::factory()->create();
        $league = League::factory()->public()->upcoming()->create([
            'buy_in'     => 100,
            'created_by' => $other->id,
        ]);

        $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/join")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Joined successfully.');

        $this->assertDatabaseHas('league_members', [
            'league_id'       => $league->id,
            'user_id'         => $user->id,
            'initial_capital' => 100,
        ]);
    }

    /** @test */
    public function join_private_league_with_correct_password(): void
    {
        $this->fakeWallbitBalance(500.0);

        [$user, $token] = $this->createUserWithToken();
        $this->connectWallbit($user);
        $this->fakeWallbitBalance(500.0);

        $other  = User::factory()->create();
        $league = League::factory()->private()->upcoming()->create([
            'buy_in'     => 50,
            'password'   => Hash::make('correctpass'),
            'created_by' => $other->id,
        ]);

        $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/join", ['password' => 'correctpass'])
            ->assertStatus(200);

        $this->assertDatabaseHas('league_members', [
            'league_id' => $league->id,
            'user_id'   => $user->id,
        ]);
    }

    /** @test */
    public function join_returns_422_when_balance_is_insufficient(): void
    {
        $this->fakeWallbitBalance(10.0);

        [$user, $token] = $this->createUserWithToken();
        $this->connectWallbit($user);
        $this->fakeWallbitBalance(10.0);

        $other  = User::factory()->create();
        $league = League::factory()->public()->upcoming()->create([
            'buy_in'     => 100,
            'created_by' => $other->id,
        ]);

        $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/join")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Insufficient WallBit balance to join this league.');

        $this->assertDatabaseMissing('league_members', [
            'league_id' => $league->id,
            'user_id'   => $user->id,
        ]);
    }

    /** @test */
    public function join_returns_422_with_wrong_password_on_private_league(): void
    {
        $this->fakeWallbitBalance(500.0);

        [$user, $token] = $this->createUserWithToken();
        $this->connectWallbit($user);
        $this->fakeWallbitBalance(500.0);

        $other  = User::factory()->create();
        $league = League::factory()->private()->upcoming()->create([
            'buy_in'     => 50,
            'password'   => Hash::make('correctpass'),
            'created_by' => $other->id,
        ]);

        $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/join", ['password' => 'wrongpass'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Invalid league password.');

        $this->assertDatabaseMissing('league_members', [
            'league_id' => $league->id,
            'user_id'   => $user->id,
        ]);
    }

    /** @test */
    public function join_returns_422_when_already_a_member(): void
    {
        $this->fakeWallbitBalance(500.0);

        [$user, $token] = $this->createUserWithToken();
        $this->connectWallbit($user);
        $this->fakeWallbitBalance(500.0);

        $other  = User::factory()->create();
        $league = League::factory()->public()->upcoming()->create([
            'buy_in'     => 100,
            'created_by' => $other->id,
        ]);

        LeagueMember::create([
            'league_id'       => $league->id,
            'user_id'         => $user->id,
            'initial_capital' => 100,
            'joined_at'       => now(),
        ]);

        $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/join")
            ->assertStatus(422)
            ->assertJsonPath('message', 'You are already a member of this league.');
    }

    /** @test */
    public function join_returns_422_when_league_is_full(): void
    {
        $this->fakeWallbitBalance(500.0);

        [$user, $token] = $this->createUserWithToken();
        $this->connectWallbit($user);
        $this->fakeWallbitBalance(500.0);

        $creator = User::factory()->create();
        $league  = League::factory()->public()->upcoming()->create([
            'buy_in'           => 50,
            'max_participants' => 1,
            'created_by'       => $creator->id,
        ]);

        // Fill the league
        $existing = User::factory()->create();
        LeagueMember::create([
            'league_id'       => $league->id,
            'user_id'         => $existing->id,
            'initial_capital' => 50,
            'joined_at'       => now(),
        ]);

        $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/join")
            ->assertStatus(422)
            ->assertJsonPath('message', 'This league is full.');
    }

    /** @test */
    public function join_returns_422_when_league_is_active_or_finished(): void
    {
        $this->fakeWallbitBalance(500.0);

        [$user, $token] = $this->createUserWithToken();
        $this->connectWallbit($user);
        $this->fakeWallbitBalance(500.0);

        $other  = User::factory()->create();
        $league = League::factory()->public()->active()->create([
            'buy_in'     => 50,
            'created_by' => $other->id,
        ]);

        $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/join")
            ->assertStatus(422)
            ->assertJsonPath('message', 'This league is not accepting new members.');
    }

    /** @test */
    public function join_returns_403_without_wallbit_key(): void
    {
        [$user, $token] = $this->createUserWithToken();

        $other  = User::factory()->create();
        $league = League::factory()->public()->upcoming()->create([
            'buy_in'     => 50,
            'created_by' => $other->id,
        ]);

        $this->withToken($token)
            ->postJson("/api/leagues/{$league->id}/join")
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/leagues/{id}/leave
    // -------------------------------------------------------------------------

    /** @test */
    public function leave_as_member_removes_membership(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $other  = User::factory()->create();

        $league = League::factory()->public()->upcoming()->create(['created_by' => $other->id]);
        LeagueMember::create([
            'league_id'       => $league->id,
            'user_id'         => $user->id,
            'initial_capital' => 100,
            'joined_at'       => now(),
        ]);

        $this->withToken($token)
            ->deleteJson("/api/leagues/{$league->id}/leave")
            ->assertStatus(200);

        $this->assertDatabaseMissing('league_members', [
            'league_id' => $league->id,
            'user_id'   => $user->id,
        ]);
    }

    /** @test */
    public function leave_returns_403_when_user_is_creator(): void
    {
        [$user, $token] = $this->createUserWithToken();

        $league = League::factory()->public()->upcoming()->create(['created_by' => $user->id]);
        LeagueMember::create([
            'league_id'       => $league->id,
            'user_id'         => $user->id,
            'initial_capital' => 100,
            'joined_at'       => now(),
        ]);

        $this->withToken($token)
            ->deleteJson("/api/leagues/{$league->id}/leave")
            ->assertStatus(403);
    }

    /** @test */
    public function leave_returns_403_when_user_is_not_a_member(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $other  = User::factory()->create();

        $league = League::factory()->public()->upcoming()->create(['created_by' => $other->id]);

        $this->withToken($token)
            ->deleteJson("/api/leagues/{$league->id}/leave")
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Artisan: leagues:update-status
    // -------------------------------------------------------------------------

    /** @test */
    public function update_status_transitions_upcoming_to_active(): void
    {
        $user   = User::factory()->create();
        $league = League::factory()->create([
            'status'     => LeagueStatus::Upcoming,
            'starts_at'  => now()->subMinutes(10),
            'ends_at'    => now()->addDays(30),
            'created_by' => $user->id,
        ]);

        $this->artisan('leagues:update-status');

        $this->assertDatabaseHas('leagues', [
            'id'     => $league->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function update_status_transitions_active_to_finished(): void
    {
        $user   = User::factory()->create();
        $league = League::factory()->create([
            'status'     => LeagueStatus::Active,
            'starts_at'  => now()->subDays(10),
            'ends_at'    => now()->subHour(),
            'created_by' => $user->id,
        ]);

        $this->artisan('leagues:update-status');

        $this->assertDatabaseHas('leagues', [
            'id'     => $league->id,
            'status' => 'finished',
        ]);
    }

    /** @test */
    public function update_status_skips_leagues_with_null_starts_at(): void
    {
        $user   = User::factory()->create();
        $league = League::factory()->create([
            'status'     => LeagueStatus::Upcoming,
            'starts_at'  => null,
            'ends_at'    => now()->addDays(30),
            'created_by' => $user->id,
        ]);

        $this->artisan('leagues:update-status');

        $this->assertDatabaseHas('leagues', [
            'id'     => $league->id,
            'status' => 'upcoming',
        ]);
    }

    /** @test */
    public function update_status_does_not_transition_future_league(): void
    {
        $user   = User::factory()->create();
        $league = League::factory()->create([
            'status'     => LeagueStatus::Upcoming,
            'starts_at'  => now()->addDay(),
            'ends_at'    => now()->addDays(30),
            'created_by' => $user->id,
        ]);

        $this->artisan('leagues:update-status');

        $this->assertDatabaseHas('leagues', [
            'id'     => $league->id,
            'status' => 'upcoming',
        ]);
    }
}
