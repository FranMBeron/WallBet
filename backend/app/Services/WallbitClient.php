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

    /**
     * Execute a trade via POST /trades.
     * Returns the response data array on success.
     * Throws RuntimeException on non-2xx or network failure.
     *
     * @return array{symbol: string, direction: string, shares: float, amount: float, status: string, created_at: string}
     * @throws \RuntimeException
     */
    public function executeTrade(
        string $apiKey,
        string $symbol,
        string $direction,
        string $orderType,
        float  $amount,
    ): array {
        $response = Http::withHeader('X-API-Key', $apiKey)
            ->post("{$this->baseUrl}/trades", [
                'symbol'     => $symbol,
                'direction'  => $direction,
                'order_type' => $orderType,
                'amount'     => $amount,
                'currency'   => 'USD',
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException(
                $response->json('message') ?? 'WallBit trade execution failed',
                $response->status()
            );
        }

        return $response->json('data');
    }

    /**
     * Get asset details via GET /assets/{symbol}.
     * Returns the data array on success.
     * Throws RuntimeException on non-2xx or network failure.
     *
     * @return array{symbol: string, price: float, name: string, sector: string}
     * @throws \RuntimeException
     */
    public function getAsset(string $apiKey, string $symbol): array
    {
        $response = Http::withHeader('X-API-Key', $apiKey)
            ->get("{$this->baseUrl}/assets/{$symbol}");

        if (!$response->successful()) {
            throw new \RuntimeException(
                $response->json('message') ?? "WallBit asset lookup failed for symbol: {$symbol}",
                $response->status()
            );
        }

        return $response->json('data');
    }

    /**
     * Get paginated transactions via GET /transactions.
     * Returns the inner data.data array (list of transaction objects).
     * Throws RuntimeException on non-2xx or network failure.
     *
     * @return array<int, array{uuid: string, source_amount: float, status: string, created_at: string}>
     * @throws \RuntimeException
     */
    public function getTransactions(string $apiKey): array
    {
        $response = Http::withHeader('X-API-Key', $apiKey)
            ->get("{$this->baseUrl}/transactions");

        if (!$response->successful()) {
            throw new \RuntimeException(
                $response->json('message') ?? 'WallBit transactions fetch failed',
                $response->status()
            );
        }

        return $response->json('data.data', []);
    }
}
