<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\ApiResponse;
use App\Models\PaymentTransaction;
use App\Services\PaystackService;
use App\Services\WalletService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PaystackService $paystackService,
        protected WalletService $walletService
    ) {}

    /**
     * Verify a payment after user returns from Paystack checkout
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'reference' => ['required', 'string'],
        ]);

        $reference = $request->input('reference');
        $user = $request->user();

        try {
            // Verify the payment with Paystack
            $paymentTransaction = $this->paystackService->verifyPayment($reference);

            // Check ownership
            if ($paymentTransaction->user_id !== $user->id) {
                return $this->error('Unauthorized to verify this payment.', Response::HTTP_FORBIDDEN);
            }

            // Process payment if verified and not yet completed
            if ($paymentTransaction->isVerified() && !$paymentTransaction->isCompleted()) {
                $this->processPaymentCompletion($paymentTransaction);
            }

            return $this->success([
                'payment_id' => $paymentTransaction->id,
                'reference' => $paymentTransaction->reference,
                'status' => $paymentTransaction->status,
                'amount' => $paymentTransaction->amount,
                'completed_at' => $paymentTransaction->completed_at,
            ], 'Payment verified successfully.');
        } catch (Exception $e) {
            Log::error("Payment verification failed: {$e->getMessage()}", [
                'reference' => $reference,
                'user_id' => $user->id,
            ]);

            return $this->error(
                'Payment verification failed. ' . $e->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * Get payment status
     */
    public function status(Request $request, string $reference): JsonResponse
    {
        $user = $request->user();

        try {
            $paymentTransaction = PaymentTransaction::where('reference', $reference)
                ->where('user_id', $user->id)
                ->firstOrFail();

            return $this->success([
                'payment_id' => $paymentTransaction->id,
                'reference' => $paymentTransaction->reference,
                'status' => $paymentTransaction->status,
                'amount' => $paymentTransaction->amount,
                'currency' => $paymentTransaction->currency,
                'verified_at' => $paymentTransaction->verified_at,
                'completed_at' => $paymentTransaction->completed_at,
                'failed_at' => $paymentTransaction->failed_at,
                'failure_reason' => $paymentTransaction->failure_reason,
            ], 'Payment status retrieved.');
        } catch (Exception $e) {
            return $this->error('Payment not found.', Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Webhook endpoint for Paystack payment notifications
     */
    public function webhook(Request $request): Response
    {
        $signature = $request->header('X-Paystack-Signature');
        $body = $request->getContent();

        // Verify webhook signature
        if (!$this->paystackService->verifyWebhookSignature($signature, $body)) {
            Log::warning('Invalid webhook signature received');
            return response('', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $event = json_decode($body, true);

            if (!$event) {
                Log::warning('Invalid webhook payload');
                return response('', Response::HTTP_BAD_REQUEST);
            }

            // Process the webhook event
            $this->paystackService->handleWebhookEvent($event);

            // If charge was successful, process the payment
            if ($event['event'] === 'charge.success') {
                $this->handleWebhookChargeSuccess($event['data'] ?? []);
            }

            return response('', Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error("Webhook processing error: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);
            return response('', Response::HTTP_OK); // Always return 200 to prevent Paystack retry
        }
    }

    /**
     * Process payment completion (credit wallet)
     */
    protected function processPaymentCompletion(PaymentTransaction $paymentTransaction): void
    {
        if ($paymentTransaction->isCompleted()) {
            return;
        }

        DB::transaction(function () use ($paymentTransaction) {
            $paymentTransaction->refresh();

            // Double-check payment is verified
            if (!$paymentTransaction->verified_at) {
                throw new Exception('Payment not verified yet.');
            }

            if ($paymentTransaction->isCompleted()) {
                return;
            }

            try {
                // Credit the wallet
                $this->walletService->credit(
                    $paymentTransaction->user,
                    $paymentTransaction->amount,
                    'wallet_funding',
                    [
                        'source' => 'paystack',
                        'channel_reference' => $paymentTransaction->reference,
                    ],
                    "Wallet funded via Paystack",
                    [
                        'payment_transaction_id' => $paymentTransaction->id,
                        'reference' => $paymentTransaction->reference,
                    ]
                );

                // Mark payment as completed
                $paymentTransaction->markCompleted();

                Log::info('Payment processed and wallet credited', [
                    'payment_id' => $paymentTransaction->id,
                    'reference' => $paymentTransaction->reference,
                    'user_id' => $paymentTransaction->user_id,
                    'amount' => $paymentTransaction->amount,
                ]);
            } catch (Exception $e) {
                Log::error('Failed to credit wallet for payment', [
                    'payment_id' => $paymentTransaction->id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        });
    }

    /**
     * Handle webhook charge.success event
     */
    protected function handleWebhookChargeSuccess(array $data): void
    {
        $reference = $data['reference'] ?? null;
        if (!$reference) {
            return;
        }

        try {
            $paymentTransaction = PaymentTransaction::where('reference', $reference)->firstOrFail();

            if ($paymentTransaction->isCompleted()) {
                return;
            }

            // Mark as verified from webhook
            if (!$paymentTransaction->verified_at) {
                $paymentTransaction->markVerified(
                    $data['authorization']['authorization_code'] ?? null,
                    $data
                );
            }

            // Process completion
            $this->processPaymentCompletion($paymentTransaction);
        } catch (Exception $e) {
            Log::error("Error handling webhook charge.success", [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
