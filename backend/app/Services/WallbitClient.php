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
}
