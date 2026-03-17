<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\WallbitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Auth — public endpoints
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Protected auth endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

// WallBit Vault — all protected
Route::prefix('wallbit')->middleware('auth:sanctum')->group(function () {
    Route::post('/connect', [WallbitController::class, 'connect'])->middleware('throttle:5,1');
    Route::get('/status', [WallbitController::class, 'status']);
    Route::delete('/disconnect', [WallbitController::class, 'disconnect']);
});
