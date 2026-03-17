<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WallbitKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WallbitVaultFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set a valid encryption key for vault operations
        config(['wallbet.encryption_key' => bin2hex(random_bytes(32))]);
    }

    private function fakeWallbitSuccess(): void
    {
        Http::fake([
            '*/balance/checking' => Http::response(['data' => [['currency' => 'USD', 'balance' => 1000]]], 200),
        ]);
    }

    private function fakeWallbitFailure(): void
    {
        Http::fake([
            '*/balance/checking' => Http::response(['message' => 'Unauthorized'], 401),
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/wallbit/connect
    // -------------------------------------------------------------------------

    /** @test */
    public function connect_with_valid_key_returns_200_and_stores_row(): void
    {
        $this->fakeWallbitSuccess();

        $user  = User::factory()->create();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/wallbit/connect', ['api_key' => 'valid-api-key-123']);

        $response->assertStatus(200)
            ->assertJson(['connected' => true]);

        $this->assertDatabaseHas('wallbit_keys', [
            'user_id'  => $user->id,
            'is_valid' => true,
        ]);
    }

    /** @test */
    public function connect_with_invalid_key_returns_422_and_no_row_inserted(): void
    {
        $this->fakeWallbitFailure();

        $user  = User::factory()->create();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/wallbit/connect', ['api_key' => 'bad-api-key']);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Invalid WallBit API key']);

        $this->assertDatabaseMissing('wallbit_keys', ['user_id' => $user->id]);
    }

    /** @test */
    public function connect_is_idempotent_upserts_existing_row(): void
    {
        $this->fakeWallbitSuccess();

        $user  = User::factory()->create();
        $token = $user->createToken('auth')->plainTextToken;

        // First connect
        $this->withToken($token)
            ->postJson('/api/wallbit/connect', ['api_key' => 'first-key'])
            ->assertStatus(200);

        // Second connect with a different key
        $this->withToken($token)
            ->postJson('/api/wallbit/connect', ['api_key' => 'second-key'])
            ->assertStatus(200);

        // Should only have one row
        $this->assertDatabaseCount('wallbit_keys', 1);
    }

    /** @test */
    public function connect_rate_limit_blocks_sixth_request_within_one_minute(): void
    {
        $this->fakeWallbitSuccess();

        $user  = User::factory()->create();
        $token = $user->createToken('auth')->plainTextToken;

        // Make 5 successful requests
        for ($i = 0; $i < 5; $i++) {
            $this->withToken($token)
                ->postJson('/api/wallbit/connect', ['api_key' => 'valid-key'])
                ->assertStatus(200);
        }

        // 6th request should be rate-limited
        $this->withToken($token)
            ->postJson('/api/wallbit/connect', ['api_key' => 'valid-key'])
            ->assertStatus(429);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/wallbit/disconnect
    // -------------------------------------------------------------------------

    /** @test */
    public function disconnect_with_existing_key_returns_204_and_deletes_row(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('auth')->plainTextToken;

        // Create a vault entry manually
        WallbitKey::create([
            'user_id'       => $user->id,
            'encrypted_key' => 'abc',
            'iv'            => 'def',
            'auth_tag'      => 'ghi',
            'is_valid'      => true,
            'connected_at'  => now(),
        ]);

        $this->withToken($token)
            ->deleteJson('/api/wallbit/disconnect')
            ->assertStatus(204);

        $this->assertDatabaseMissing('wallbit_keys', ['user_id' => $user->id]);
    }

    /** @test */
    public function disconnect_without_existing_key_is_idempotent_and_returns_204(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->deleteJson('/api/wallbit/disconnect')
            ->assertStatus(204);
    }

    // -------------------------------------------------------------------------
    // GET /api/wallbit/status
    // -------------------------------------------------------------------------

    /** @test */
    public function status_returns_connected_true_when_valid_key_exists(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('auth')->plainTextToken;
        $now   = now();

        WallbitKey::create([
            'user_id'       => $user->id,
            'encrypted_key' => 'abc',
            'iv'            => 'def',
            'auth_tag'      => 'ghi',
            'is_valid'      => true,
            'connected_at'  => $now,
        ]);

        $response = $this->withToken($token)
            ->getJson('/api/wallbit/status');

        $response->assertStatus(200)
            ->assertJson(['connected' => true])
            ->assertJsonStructure(['connected', 'connected_at']);

        $this->assertNotNull($response->json('connected_at'));
    }

    /** @test */
    public function status_returns_connected_false_when_no_key_exists(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/wallbit/status');

        $response->assertStatus(200)
            ->assertJson([
                'connected'    => false,
                'connected_at' => null,
            ]);
    }

    /** @test */
    public function status_without_token_returns_401(): void
    {
        $this->getJson('/api/wallbit/status')
            ->assertStatus(401);
    }
}
