<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DemoLoginController extends Controller
{
    /**
     * Issue a Sanctum Bearer token for the demo user.
     * Only available when APP_DEMO_MODE=true.
     */
    public function login(): JsonResponse
    {
        abort_unless(config('app.demo_mode'), 403, 'Demo mode is not enabled.');

        $user = User::where('email', 'demo@wallbet.io')->firstOrFail();

        // Revoke any existing demo tokens to avoid accumulation
        $user->tokens()->where('name', 'demo')->delete();

        $token = $user->createToken('demo')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => new UserResource($user),
        ]);
    }
}
