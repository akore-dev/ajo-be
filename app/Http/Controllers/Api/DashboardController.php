<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $wallet = $user->wallet()->firstOrCreate([], [
            'balance' => 0,
            'ledger_balance' => 0,
            'currency' => 'NGN',
            'status' => 'active',
        ]);

        $activeCirclesCount = $user->circles()
            ->where('circles.status', 'active')
            ->count();

        $totalContributed = $user->contributions()
            ->where('status', 'paid')
            ->sum('amount');

        $pendingContributionsCount = $user->contributions()
            ->where('status', 'pending')
            ->count();

        $recentContributions = $user->contributions()
            ->with('circle:id,name,contribution_amount')
            ->latest()
            ->take(5)
            ->get();

        $recentWalletTransactions = $wallet->transactions()
            ->latest()
            ->take(5)
            ->get();

        return $this->success([
            'metrics' => [
                'wallet_balance' => (float) $wallet->balance,
                'currency' => $wallet->currency,
                'active_circles_count' => $activeCirclesCount,
                'total_contributed' => (float) $totalContributed,
                'pending_contributions_count' => $pendingContributionsCount,
            ],
            'recent_activity' => [
                'contributions' => $recentContributions,
                'wallet_transactions' => $recentWalletTransactions,
            ],
        ], 'Dashboard statistics retrieved successfully.');
    }
}

