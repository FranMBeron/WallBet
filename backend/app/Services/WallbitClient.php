<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WallbitClient
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('wallbet.api_base_url');
    }

    /**
     * Validate a WallBit API key by calling GET /balance/checking.
     * Returns true if the response is 2xx, false otherwise.
     */
    public function validateKey(string $apiKey): bool
    {
        try {
            $response = Http::withHeader('X-API-Key', $apiKey)
                ->get("{$this->baseUrl}/balance/checking");

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Retrieve the USD checking balance for a given API key.
     * Returns the balance as a float, or 0.0 on failure.
     */
    public function getBalance(string $apiKey): float
    {
        try {
            $response = Http::withHeader('X-API-Key', $apiKey)
                ->get("{$this->baseUrl}/balance/checking");

            if (!$response->successful()) {
                return 0.0;
            }

            $data = $response->json('data', []);

            foreach ($data as $account) {
                if (isset($account['currency']) && strtoupper($account['currency']) === 'USD') {
                    return (float) ($account['balance'] ?? 0.0);
                }
            }

            return 0.0;
        } catch (\Throwable) {
            return 0.0;
        }
    }
}
