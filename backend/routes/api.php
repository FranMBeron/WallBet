<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Auth\DemoLoginController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\LeagueController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\TradeController;
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
    Route::post('/demo-login', [DemoLoginController::class, 'login']);

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

// Leagues — all protected
// IMPORTANT: /my and /invite/{code} MUST be declared BEFORE /{league} to prevent shadowing
Route::prefix('leagues')->middleware('auth:sanctum')->group(function () {
    Route::get('/',                   [LeagueController::class, 'index']);
    Route::post('/',                  [LeagueController::class, 'store']);
    Route::get('/my',                 [LeagueController::class, 'my']);
    Route::get('/invite/{code}',      [LeagueController::class, 'findByCode']);
    Route::get('/{league}',           [LeagueController::class, 'show']);
    Route::post('/{league}/join',     [LeagueController::class, 'join'])->middleware('wallbit.connected');
    Route::delete('/{league}/leave',  [LeagueController::class, 'leave']);

    Route::post('/{league}/trades',   [TradeController::class, 'execute'])->middleware('league.member');
    Route::get('/{league}/trades',    [TradeController::class, 'index'])->middleware('league.member');
    Route::get('/{league}/assets/{symbol}', [TradeController::class, 'previewAsset'])->middleware('league.member');
    Route::get('/{league}/portfolio', [PortfolioController::class, 'show'])->middleware('league.member');

    // Competition — IMPORTANT: leaderboard/history MUST be declared BEFORE leaderboard to prevent shadowing
    Route::get('/{league}/leaderboard/history', [LeaderboardController::class, 'history'])->middleware('league.member');
    Route::get('/{league}/leaderboard',         [LeaderboardController::class, 'index'])->middleware('league.member');
    Route::get('/{league}/analytics',           [AnalyticsController::class, 'index'])->middleware('league.member');
    Route::get('/{league}/compare',             [CompareController::class, 'index'])->middleware('league.member');
});
