<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // POST /api/auth/register
    // -------------------------------------------------------------------------

    /** @test */
    public function register_with_valid_data_returns_201_with_token_and_user(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'     => 'Alice Test',
            'username' => 'alice',
            'email'    => 'alice@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'email', 'username', 'display_name', 'avatar_url'],
            ])
            ->assertJsonMissing(['password']);

        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
    }

    /** @test */
    public function register_with_duplicate_email_returns_422(): void
    {
        User::factory()->create(['email' => 'alice@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name'     => 'Alice Test',
            'username' => 'alice2',
            'email'    => 'alice@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function register_with_weak_password_returns_422(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'     => 'Bob Test',
            'username' => 'bob',
            'email'    => 'bob@example.com',
            'password' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    // -------------------------------------------------------------------------
    // POST /api/auth/login
    // -------------------------------------------------------------------------

    /** @test */
    public function login_with_valid_credentials_returns_200_with_token(): void
    {
        $user = User::factory()->create([
            'email'    => 'user@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'email', 'username', 'display_name'],
            ]);
    }

    /** @test */
    public function login_with_wrong_password_returns_401_with_generic_message(): void
    {
        User::factory()->create([
            'email'    => 'user@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials']);
    }

    /** @test */
    public function login_with_unknown_email_returns_401_with_same_generic_message(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'nobody@example.com',
            'password' => 'anypassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials']);
    }

    // -------------------------------------------------------------------------
    // POST /api/auth/logout
    // -------------------------------------------------------------------------

    /** @test */
    public function logout_revokes_current_token_and_subsequent_request_returns_401(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertStatus(200);

        // Same token should now be rejected
        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertStatus(401);
    }

    /** @test */
    public function logout_without_token_returns_401(): void
    {
        $this->postJson('/api/auth/logout')
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // GET /api/auth/me
    // -------------------------------------------------------------------------

    /** @test */
    public function me_returns_authenticated_user_without_password(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'id'       => $user->id,
                'email'    => $user->email,
                'username' => $user->username,
            ])
            ->assertJsonMissing(['password']);
    }

    /** @test */
    public function me_with_revoked_token_returns_401(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('auth')->plainTextToken;

        // Revoke the token
        $user->tokens()->delete();

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertStatus(401);
    }
}
