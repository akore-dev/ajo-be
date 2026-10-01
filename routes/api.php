<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CircleController;
use App\Http\Controllers\Api\CircleMemberController;
use App\Http\Controllers\Api\ContributionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PayoutController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the Application framework within a group which
| is assigned the "api" middleware group.
|
*/

// ==========================================
// Public Authentication Routes
// ==========================================
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// ==========================================
// Webhooks (Public, unsigned)
// ==========================================
Route::post('/webhooks/paystack', [PaymentController::class, 'webhook'])->name('payment.webhook');

// ==========================================
// Protected Routes (Sanctum Authentication)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {

    // Auth & Profile Management
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    // Dashboard Analytics & Metrics
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Wallet Management & Funding
    Route::prefix('wallet')->group(function () {
        Route::get('/', [WalletController::class, 'show']);
        Route::post('/fund', [WalletController::class, 'fund']);
    });

    // Payment Management
    Route::prefix('payments')->group(function () {
        Route::get('/verify', [PaymentController::class, 'verify'])->name('payment.verify');
        Route::get('/{reference}', [PaymentController::class, 'status'])->name('payment.status');
    });

    // ROSCA Circle Management
    Route::apiResource('circles', CircleController::class)->except(['update', 'destroy']);
    Route::post('/circles/{circle}/join', [CircleController::class, 'join']);

    // Circle Members
    Route::prefix('circles/{circle}/members')->group(function () {
        Route::get('/', [CircleMemberController::class, 'index']);
        Route::patch('/{user}/position', [CircleMemberController::class, 'updatePosition']);
        Route::delete('/{user}', [CircleMemberController::class, 'destroy']);
    });

    // Contributions
    Route::prefix('contributions')->group(function () {
        Route::get('/', [ContributionController::class, 'index']);
        Route::post('/', [ContributionController::class, 'store']);
    });

    // Payouts
    Route::prefix('payouts')->group(function () {
        Route::get('/', [PayoutController::class, 'index']);
        Route::get('/{payout}', [PayoutController::class, 'show']);
        Route::post('/circles/{circle}/process', [PayoutController::class, 'process']);
    });
});
