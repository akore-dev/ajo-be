<?php

namespace App\Services;

use App\Models\PaymentTransaction;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PaystackService
{
    private string $baseUrl = 'https://api.paystack.co';
    private string $secretKey;
    private string $publicKey;
    private string $webhookSecret;

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key');
        $this->publicKey = config('services.paystack.public_key');
        $this->webhookSecret = config('services.paystack.webhook_secret');

        if (!$this->secretKey || !$this->publicKey) {
            throw new RuntimeException('Paystack API keys not configured.');
        }
    }

    /**
     * Initialize a payment transaction with Paystack
     */
    public function initializePayment(
        User $user,
        int $amount,
        string $callbackUrl,
        ?string $idempotencyKey = null
    ): PaymentTransaction {
        $idempotencyKey = $idempotencyKey ?? 'PAYMENT-' . Str::random(16);

        // Check for duplicate payment with idempotency key
        $existingPayment = PaymentTransaction::where('idempotency_key', $idempotencyKey)
            ->where('status', '!=', 'failed')
            ->where('status', '!=', 'abandoned')
            ->first();

        if ($existingPayment) {
            Log::info("Payment with idempotency key {$idempotencyKey} already exists.", [
                'payment_id' => $existingPayment->id,
            ]);
            return $existingPayment;
        }

        try {
            $reference = 'PAY-' . strtoupper(Str::random(12));

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->secretKey}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/transaction/initialize", [
                'amount' => $amount,
                'email' => $user->email,
                'reference' => $reference,
                'callback_url' => $callbackUrl,
                'metadata' => [
                    'user_id' => $user->id,
                    'currency' => 'NGN',
                ],
            ]);

            if (!$response->successful()) {
                $error = $response->json('message', 'Failed to initialize payment');
                Log::error("Paystack initialization failed: {$error}", [
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'response' => $response->json(),
                ]);
                throw new Exception($error);
            }

            $data = $response->json('data');

            $paymentTransaction = PaymentTransaction::create([
                'user_id' => $user->id,
                'reference' => $reference,
                'access_code' => $data['access_code'] ?? null,
                'checkout_url' => $data['authorization_url'] ?? null,
                'status' => 'pending',
                'gateway' => 'paystack',
                'amount' => $amount,
                'currency' => 'NGN',
                'email' => $user->email,
                'metadata' => [
                    'callback_url' => $callbackUrl,
                ],
                'idempotency_key' => $idempotencyKey,
                'gateway_response' => $data,
            ]);

            Log::info("Payment initialized successfully", [
                'payment_id' => $paymentTransaction->id,
                'reference' => $reference,
            ]);

            return $paymentTransaction;
        } catch (Exception $e) {
            Log::error("PaystackService::initializePayment error: {$e->getMessage()}", [
                'user_id' => $user->id,
                'amount' => $amount,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Verify a payment with Paystack
     */
    public function verifyPayment(string $reference): PaymentTransaction
    {
        try {
            $paymentTransaction = PaymentTransaction::where('reference', $reference)->firstOrFail();

            if ($paymentTransaction->isCompleted()) {
                Log::info("Payment already verified", ['reference' => $reference]);
                return $paymentTransaction;
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->secretKey}",
            ])->get("{$this->baseUrl}/transaction/verify/{$reference}");

            if (!$response->successful()) {
                $error = $response->json('message', 'Payment verification failed');
                $paymentTransaction->markFailed($error, $response->json());
                Log::error("Paystack verification failed", [
                    'reference' => $reference,
                    'response' => $response->json(),
                ]);
                throw new Exception($error);
            }

            $data = $response->json('data');

            if ($data['status'] !== 'success') {
                $reason = $data['gateway_response'] ?? 'Payment unsuccessful';
                $paymentTransaction->markFailed($reason, $data);
                Log::warning("Payment verification unsuccessful", [
                    'reference' => $reference,
                    'status' => $data['status'],
                ]);
                throw new Exception("Payment was not successful: {$reason}");
            }

            // Verify amount matches
            if ($data['amount'] !== $paymentTransaction->amount) {
                $paymentTransaction->markFailed('Amount mismatch', $data);
                Log::error("Payment amount mismatch", [
                    'reference' => $reference,
                    'expected' => $paymentTransaction->amount,
                    'actual' => $data['amount'],
                ]);
                throw new Exception('Payment amount does not match');
            }

            $paymentTransaction->markVerified(
                $data['authorization']['authorization_code'] ?? null,
                $data
            );

            Log::info("Payment verified successfully", [
                'reference' => $reference,
                'payment_id' => $paymentTransaction->id,
            ]);

            return $paymentTransaction;
        } catch (Exception $e) {
            Log::error("PaystackService::verifyPayment error: {$e->getMessage()}", [
                'reference' => $reference,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Verify webhook signature from Paystack
     */
    public function verifyWebhookSignature(string $signature, string $body): bool
    {
        if (!$this->webhookSecret) {
            Log::warning('Paystack webhook secret not configured');
            return false;
        }

        $hash = hash_hmac('sha512', $body, $this->webhookSecret);
        $isValid = hash_equals($hash, $signature);

        if (!$isValid) {
            Log::warning('Invalid Paystack webhook signature', [
                'expected' => $signature,
                'computed' => $hash,
            ]);
        }

        return $isValid;
    }

    /**
     * Handle payment webhook event from Paystack
     */
    public function handleWebhookEvent(array $event): void
    {
        $eventType = $event['event'] ?? null;
        $data = $event['data'] ?? [];

        Log::info("Processing Paystack webhook event", [
            'event' => $eventType,
            'reference' => $data['reference'] ?? null,
        ]);

        match ($eventType) {
            'charge.success' => $this->handleChargeSuccess($data),
            'charge.failed' => $this->handleChargeFailed($data),
            'charge.dispute.create' => $this->handleDisputeCreate($data),
            'charge.dispute.resolve' => $this->handleDisputeResolve($data),
            default => Log::info("Unhandled webhook event: {$eventType}"),
        };
    }

    /**
     * Handle successful charge
     */
    private function handleChargeSuccess(array $data): void
    {
        $reference = $data['reference'] ?? null;
        if (!$reference) {
            Log::warning('Webhook charge.success event missing reference');
            return;
        }

        try {
            $paymentTransaction = PaymentTransaction::where('reference', $reference)->firstOrFail();

            if ($paymentTransaction->isCompleted()) {
                Log::info("Payment already processed", ['reference' => $reference]);
                return;
            }

            $paymentTransaction->markCompleted($data);

            Log::info("Webhook charge.success processed", [
                'reference' => $reference,
                'payment_id' => $paymentTransaction->id,
            ]);
        } catch (Exception $e) {
            Log::error("Error handling charge.success webhook", [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle failed charge
     */
    private function handleChargeFailed(array $data): void
    {
        $reference = $data['reference'] ?? null;
        if (!$reference) {
            Log::warning('Webhook charge.failed event missing reference');
            return;
        }

        try {
            $paymentTransaction = PaymentTransaction::where('reference', $reference)->firstOrFail();

            if ($paymentTransaction->isFailed()) {
                return;
            }

            $reason = $data['gateway_response'] ?? 'Payment failed';
            $paymentTransaction->markFailed($reason, $data);

            Log::info("Webhook charge.failed processed", [
                'reference' => $reference,
                'reason' => $reason,
            ]);
        } catch (Exception $e) {
            Log::error("Error handling charge.failed webhook", [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle dispute creation
     */
    private function handleDisputeCreate(array $data): void
    {
        Log::info("Dispute created on Paystack", [
            'dispute_id' => $data['id'] ?? null,
            'reference' => $data['transaction']['reference'] ?? null,
        ]);
    }

    /**
     * Handle dispute resolution
     */
    private function handleDisputeResolve(array $data): void
    {
        Log::info("Dispute resolved on Paystack", [
            'dispute_id' => $data['id'] ?? null,
            'resolution' => $data['resolution'] ?? null,
        ]);
    }
}
