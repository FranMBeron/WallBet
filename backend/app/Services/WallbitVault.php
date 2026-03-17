<?php

namespace App\Services;

use App\Models\WallbitKey;
use RuntimeException;

class WallbitVault
{
    private string $key;

    public function __construct()
    {
        $this->key = hex2bin(config('wallbet.encryption_key'));
    }

    /**
     * Encrypt an API key using AES-256-GCM.
     *
     * @return array{encrypted_key: string, iv: string, auth_tag: string}
     */
    public function encrypt(string $apiKey): array
    {
        $iv = random_bytes(12); // 96-bit IV for GCM
        $tag = '';

        $ciphertext = openssl_encrypt(
            $apiKey,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed');
        }

        return [
            'encrypted_key' => bin2hex($ciphertext),
            'iv'            => bin2hex($iv),
            'auth_tag'      => bin2hex($tag),
        ];
    }

    /**
     * Decrypt a WallbitKey model's stored encrypted key.
     *
     * @throws RuntimeException if auth tag verification fails
     */
    public function decrypt(WallbitKey $wallbitKey): string
    {
        $plain = openssl_decrypt(
            hex2bin($wallbitKey->encrypted_key),
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            hex2bin($wallbitKey->iv),
            hex2bin($wallbitKey->auth_tag)
        );

        if ($plain === false) {
            throw new RuntimeException('Decryption failed: auth tag mismatch');
        }

        return $plain;
    }
}
