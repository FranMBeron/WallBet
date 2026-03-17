<?php

namespace Tests\Feature;

use App\Models\League;
use App\Models\LeagueMember;
use App\Models\PortfolioSnapshot;
use App\Models\User;
use App\Models\WallbitKey;
use App\Services\WallbitVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SnapshotTest extends TestCase
{
    use RefreshDatabase;

    private string $encKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->encKey = bin2hex(random_bytes(32));
        config(['wallbet.encryption_key' => $this->encKey]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create a user and directly insert a valid WallbitKey for them
     * (bypasses HTTP to avoid fake-replacement issues).
     */
    private function createUserWithWallbitKey(): User
    {
        $user  = User::factory()->create();
        $vault = new WallbitVault();

        $encrypted = $vault->encrypt('valid-key-123');

        WallbitKey::create([
            'user_id'       => $user->id,
            'encrypted_key' => $encrypted['encrypted_key'],
            'iv'            => $encrypted['iv'],
            'auth_tag'      => $encrypted['auth_tag'],
            'is_valid'      => true,
            'connected_at'  => now(),
        ]);

        return $user->fresh();
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

    private function fakeAssetPrices(): void
    {
        Http::fake([
            '*/assets/*' => Http::response([
                'data' => ['symbol' => 'AAPL', 'price' => 200.0, 'name' => 'Apple', 'sector' => 'Technology'],
            ], 200),
        ]);
    }

    // -------------------------------------------------------------------------
    // snapshots:capture command
    // -------------------------------------------------------------------------

    #[Test]
    public function capture_command_skips_upcoming_and_finished_leagues_and_processes_active_only(): void
    {
        $creator = User::factory()->create();

        $activeLeague   = League::factory()->active()->create(['created_by' => $creator->id]);
        $upcomingLeague = League::factory()->upcoming()->create(['created_by' => $creator->id]);
        $finishedLeague = League::factory()->finished()->create(['created_by' => $creator->id]);

        $activeUser   = $this->createUserWithWallbitKey();
        $upcomingUser = $this->createUserWithWallbitKey();
        $finishedUser = $this->createUserWithWallbitKey();

        $this->joinLeague($activeUser, $activeLeague);
        $this->joinLeague($upcomingUser, $upcomingLeague);
        $this->joinLeague($finishedUser, $finishedLeague);

        $this->fakeAssetPrices();

        $this->artisan('snapshots:capture')->assertSuccessful();

        // Only the active league's member should have a snapshot
        $this->assertDatabaseCount('portfolio_snapshots', 1);
        $this->assertDatabaseHas('portfolio_snapshots', [
            'league_id' => $activeLeague->id,
            'user_id'   => $activeUser->id,
        ]);
    }

    #[Test]
    public function capture_command_running_twice_in_same_hour_produces_no_duplicate_row(): void
    {
        $creator = User::factory()->create();
        $league  = League::factory()->active()->create(['created_by' => $creator->id]);
        $user    = $this->createUserWithWallbitKey();
        $this->joinLeague($user, $league);

        $this->fakeAssetPrices();
        $this->artisan('snapshots:capture')->assertSuccessful();

        $this->fakeAssetPrices();
        $this->artisan('snapshots:capture')->assertSuccessful();

        // Only ONE snapshot row — the second run should have upserted it
        $this->assertDatabaseCount('portfolio_snapshots', 1);
    }

    #[Test]
    public function capture_command_per_member_wallbit_failure_logs_error_and_does_not_prevent_other_members(): void
    {
        $creator = User::factory()->create();
        $league  = League::factory()->active()->create(['created_by' => $creator->id]);

        $userA = $this->createUserWithWallbitKey();
        $userB = $this->createUserWithWallbitKey();
        $userC = $this->createUserWithWallbitKey();

        $this->joinLeague($userA, $league);
        $this->joinLeague($userB, $league);
        $this->joinLeague($userC, $league);

        // Simulate userB's WallBit key being invalid → SnapshotService will throw
        WallbitKey::where('user_id', $userB->id)->update(['is_valid' => false]);

        $this->fakeAssetPrices();

        $this->artisan('snapshots:capture')->assertSuccessful();

        // A and C should have snapshots; B's capture should fail and be skipped
        $this->assertDatabaseHas('portfolio_snapshots', [
            'league_id' => $league->id,
            'user_id'   => $userA->id,
        ]);
        $this->assertDatabaseHas('portfolio_snapshots', [
            'league_id' => $league->id,
            'user_id'   => $userC->id,
        ]);
        $this->assertDatabaseMissing('portfolio_snapshots', [
            'league_id' => $league->id,
            'user_id'   => $userB->id,
        ]);
    }
}
