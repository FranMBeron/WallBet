<?php

namespace App\Http\Middleware;

use App\Models\WallbitKey;
use Closure;
use Illuminate\Http\Request;

class EnsureWallbitConnected
{
    /**
     * Abort with 403 if the authenticated user has no valid WallBit key.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $connected = WallbitKey::where('user_id', $request->user()->id)
            ->where('is_valid', true)
            ->exists();

        if (!$connected) {
            abort(403, 'WallBit account not connected.');
        }

        return $next($request);
    }
}
