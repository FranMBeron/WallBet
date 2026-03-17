<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConnectWallbitRequest;
use App\Models\WallbitKey;
use App\Services\WallbitClient;
use App\Services\WallbitVault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WallbitController extends Controller
{
    public function __construct(
        private readonly WallbitClient $client,
        private readonly WallbitVault  $vault,
    ) {}

    /**
     * Validate an API key against WallBit, encrypt it, and upsert the vault entry.
     */
    public function connect(ConnectWallbitRequest $request): JsonResponse
    {
        $apiKey = $request->api_key;

        if (!$this->client->validateKey($apiKey)) {
            return response()->json([
                'message' => 'Invalid WallBit API key',
            ], 422);
        }

        $encrypted = $this->vault->encrypt($apiKey);

        WallbitKey::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'encrypted_key' => $encrypted['encrypted_key'],
                'iv'            => $encrypted['iv'],
                'auth_tag'      => $encrypted['auth_tag'],
                'is_valid'      => true,
                'connected_at'  => now(),
            ]
        );

        return response()->json(['connected' => true]);
    }

    /**
     * Return the vault connection status for the authenticated user.
     */
    public function status(Request $request): JsonResponse
    {
        $key = WallbitKey::where('user_id', $request->user()->id)
            ->where('is_valid', true)
            ->first();

        return response()->json([
            'connected'    => $key !== null,
            'connected_at' => $key?->connected_at?->toIso8601String(),
        ]);
    }

    /**
     * Delete the vault entry for the authenticated user (idempotent).
     */
    public function disconnect(Request $request): \Illuminate\Http\Response
    {
        WallbitKey::where('user_id', $request->user()->id)->delete();

        return response()->noContent();
    }
}
