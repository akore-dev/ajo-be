<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\ApiResponse;
use App\Http\Requests\FundWalletRequest;
use App\Services\PaystackService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WalletController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PaystackService $paystackService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $wallet = $request->user()->wallet()->firstOrCreate([], [
            'balance' => 0,
            'ledger_balance' => 0,
            'currency' => 'NGN',
            'status' => 'active',
        ]);

        $transactions = $wallet->transactions()->latest()->take(10)->get();

        return $this->success([
            'wallet' => $wallet,
            'recent_transactions' => $transactions,
        ], 'Wallet details retrieved.');
    }

    public function fund(FundWalletRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        if ($data['payment_gateway'] !== 'paystack') {
            return $this->error('Only Paystack is currently supported.', Response::HTTP_BAD_REQUEST);
        }

        try {
            // Get or create wallet
            $wallet = $user->wallet()->firstOrCreate([], [
                'balance' => 0,
                'ledger_balance' => 0,
                'currency' => 'NGN',
                'status' => 'active',
            ]);

            // Initialize payment with Paystack
            $callbackUrl = $data['callback_url'] ?? route('api.payment.verify');

            $paymentTransaction = $this->paystackService->initializePayment(
                $user,
                $data['amount'],
                $callbackUrl
            );

            // Link payment to wallet
            $paymentTransaction->update(['wallet_id' => $wallet->id]);

            return $this->success([
                'payment_id' => $paymentTransaction->id,
                'reference' => $paymentTransaction->reference,
                'access_code' => $paymentTransaction->access_code,
                'checkout_url' => $paymentTransaction->checkout_url,
                'amount' => $paymentTransaction->amount,
                'currency' => $paymentTransaction->currency,
            ], 'Payment checkout initialized.', Response::HTTP_ACCEPTED);
        } catch (Exception $e) {
            Log::error("Wallet fund error: {$e->getMessage()}", [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error(
                'Failed to initialize payment. Please try again.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}

