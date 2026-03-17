<?php

namespace Tests\Unit;

use App\Models\WallbitKey;
use App\Services\WallbitVault;
use RuntimeException;
use Tests\TestCase;

class WallbitVaultTest extends TestCase
{
    private WallbitVault $vault;

    protected function setUp(): void
    {
        parent::setUp();

        // Generate a 32-byte key and set it in config for testing
        $key = bin2hex(random_bytes(32));
        config(['wallbet.encryption_key' => $key]);

        $this->vault = new WallbitVault();
    }

    /** @test */
    public function encrypt_and_decrypt_round_trip_returns_original_plaintext(): void
    {
        $original = 'test-api-key-12345';

        $encrypted = $this->vault->encrypt($original);

        // Build a WallbitKey-like model with the encrypted data
        $wallbitKey = new WallbitKey();
        $wallbitKey->encrypted_key = $encrypted['encrypted_key'];
        $wallbitKey->iv = $encrypted['iv'];
        $wallbitKey->auth_tag = $encrypted['auth_tag'];

        $decrypted = $this->vault->decrypt($wallbitKey);

        $this->assertSame($original, $decrypted);
    }

    /** @test */
    public function encrypt_produces_different_ciphertext_each_call_due_to_random_iv(): void
    {
        $apiKey = 'same-api-key';

        $first  = $this->vault->encrypt($apiKey);
        $second = $this->vault->encrypt($apiKey);

        // IVs must differ (random per call)
        $this->assertNotSame($first['iv'], $second['iv']);
        // Ciphertexts must differ because IVs differ
        $this->assertNotSame($first['encrypted_key'], $second['encrypted_key']);
    }

    /** @test */
    public function tampered_auth_tag_throws_runtime_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');

        $encrypted = $this->vault->encrypt('some-api-key');

        // Tamper the auth_tag by flipping the first byte
        $tagBytes = hex2bin($encrypted['auth_tag']);
        $tagBytes[0] = chr(ord($tagBytes[0]) ^ 0xFF); // flip all bits of first byte
        $tamperedTag = bin2hex($tagBytes);

        $wallbitKey = new WallbitKey();
        $wallbitKey->encrypted_key = $encrypted['encrypted_key'];
        $wallbitKey->iv = $encrypted['iv'];
        $wallbitKey->auth_tag = $tamperedTag;

        $this->vault->decrypt($wallbitKey);
    }

    /** @test */
    public function encrypt_returns_hex_encoded_strings(): void
    {
        $encrypted = $this->vault->encrypt('api-key-value');

        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $encrypted['encrypted_key']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $encrypted['iv']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $encrypted['auth_tag']);

        // IV should be 24 hex chars = 12 bytes
        $this->assertSame(24, strlen($encrypted['iv']));
        // auth_tag should be 32 hex chars = 16 bytes
        $this->assertSame(32, strlen($encrypted['auth_tag']));
    }
}
