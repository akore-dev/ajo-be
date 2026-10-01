<?php

namespace Tests\Feature;

use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\PaystackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $this->wallet = Wallet::factory()->create([
            'user_id' => $this->user->id,
            'balance' => 0,
        ]);
    }

    /**
     * Test payment initialization
     */
    public function test_user_can_initialize_payment(): void
    {
        Http::fake([
            'api.paystack.co/*' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'access_code' => 'test_access_code',
                    'reference' => 'PAY-TEST123',
                ],
            ]),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/wallet/fund', [
                'amount' => 50000,
                'payment_gateway' => 'paystack',
            ]);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'payment_id',
                    'reference',
                    'access_code',
                    'checkout_url',
                    'amount',
                    'currency',
                ],
            ])
            ->assertJsonPath('data.amount', 50000)
            ->assertJsonPath('data.currency', 'NGN');

        $this->assertDatabaseHas('payment_transactions', [
            'user_id' => $this->user->id,
            'amount' => 50000,
            'status' => 'pending',
        ]);
    }

    /**
     * Test idempotency in payment initialization
     */
    public function test_same_idempotency_key_returns_same_payment(): void
    {
        Http::fake([
            'api.paystack.co/*' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'access_code' => 'test_access_code',
                    'reference' => 'PAY-TEST123',
                ],
            ]),
        ]);

        $response1 = $this->actingAs($this->user)
            ->postJson('/api/wallet/fund', [
                'amount' => 50000,
                'payment_gateway' => 'paystack',
            ]);

        $paymentId1 = $response1->json('data.payment_id');

        Http::fake([
            'api.paystack.co/*' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test2',
                    'access_code' => 'test_access_code_2',
                    'reference' => 'PAY-TEST456',
                ],
            ]),
        ]);

        // Make same request - should return same payment
        $response2 = $this->actingAs($this->user)
            ->postJson('/api/wallet/fund', [
                'amount' => 50000,
                'payment_gateway' => 'paystack',
            ]);

        $paymentId2 = $response2->json('data.payment_id');

        $this->assertEquals($paymentId1, $paymentId2);
    }

    /**
     * Test payment verification
     */
    public function test_user_can_verify_payment(): void
    {
        // Create a pending payment
        $payment = PaymentTransaction::create([
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'reference' => 'PAY-TEST123',
            'status' => 'pending',
            'gateway' => 'paystack',
            'amount' => 50000,
            'currency' => 'NGN',
            'email' => $this->user->email,
            'access_code' => 'test_access_code',
        ]);

        Http::fake([
            'api.paystack.co/*' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'id' => 12345,
                    'reference' => 'PAY-TEST123',
                    'amount' => 50000,
                    'status' => 'success',
                    'authorization' => [
                        'authorization_code' => 'AUTH_123456',
                        'bin' => '408400',
                        'last4' => '0001',
                    ],
                ],
            ]),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/payments/verify?reference=PAY-TEST123');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('payment_transactions', [
            'reference' => 'PAY-TEST123',
            'status' => 'completed',
        ]);

        // Verify wallet was credited
        $this->wallet->refresh();
        $this->assertEquals(50000, $this->wallet->balance);
    }

    /**
     * Test payment status check
     */
    public function test_user_can_check_payment_status(): void
    {
        $payment = PaymentTransaction::create([
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'reference' => 'PAY-TEST123',
            'status' => 'completed',
            'gateway' => 'paystack',
            'amount' => 50000,
            'currency' => 'NGN',
            'email' => $this->user->email,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/payments/PAY-TEST123');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.amount', 50000);
    }

    /**
     * Test user cannot verify another user's payment
     */
    public function test_user_cannot_verify_another_users_payment(): void
    {
        $otherUser = User::factory()->create();

        $payment = PaymentTransaction::create([
            'user_id' => $otherUser->id,
            'reference' => 'PAY-TEST123',
            'status' => 'pending',
            'gateway' => 'paystack',
            'amount' => 50000,
            'currency' => 'NGN',
            'email' => $otherUser->email,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/payments/verify?reference=PAY-TEST123');

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    /**
     * Test webhook signature verification
     */
    public function test_invalid_webhook_signature_is_rejected(): void
    {
        $response = $this->postJson('/api/webhooks/paystack',
            ['event' => 'charge.success'],
            ['X-Paystack-Signature' => 'invalid_signature']
        );

        $response->assertStatus(401);
    }

    /**
     * Test successful webhook handling
     */
    public function test_webhook_processes_successful_charge(): void
    {
        $payment = PaymentTransaction::create([
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'reference' => 'PAY-TEST123',
            'status' => 'pending',
            'gateway' => 'paystack',
            'amount' => 50000,
            'currency' => 'NGN',
            'email' => $this->user->email,
        ]);

        $webhookBody = json_encode([
            'event' => 'charge.success',
            'data' => [
                'id' => 12345,
                'reference' => 'PAY-TEST123',
                'amount' => 50000,
                'status' => 'success',
                'authorization' => [
                    'authorization_code' => 'AUTH_123456',
                ],
            ],
        ]);

        // Generate valid signature
        $secret = config('services.paystack.webhook_secret');
        $signature = hash_hmac('sha512', $webhookBody, $secret);

        $response = $this->postJson('/api/webhooks/paystack',
            json_decode($webhookBody, true),
            ['X-Paystack-Signature' => $signature]
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('payment_transactions', [
            'reference' => 'PAY-TEST123',
            'status' => 'completed',
        ]);

        // Verify wallet was credited
        $this->wallet->refresh();
        $this->assertEquals(50000, $this->wallet->balance);
    }

    /**
     * Test payment with invalid gateway
     */
    public function test_payment_with_unsupported_gateway_is_rejected(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/wallet/fund', [
                'amount' => 50000,
                'payment_gateway' => 'flutterwave',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    /**
     * Test payment amount validation
     */
    public function test_minimum_payment_amount_is_enforced(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/wallet/fund', [
                'amount' => 50,
                'payment_gateway' => 'paystack',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /**
     * Test duplicate prevention with failed payment
     */
    public function test_duplicate_payment_not_created_if_previous_failed(): void
    {
        // Create a failed payment
        $failedPayment = PaymentTransaction::create([
            'user_id' => $this->user->id,
            'reference' => 'PAY-TEST123',
            'status' => 'failed',
            'gateway' => 'paystack',
            'amount' => 50000,
            'currency' => 'NGN',
            'email' => $this->user->email,
            'failure_reason' => 'Insufficient funds',
            'idempotency_key' => 'IDEM-KEY-123',
        ]);

        Http::fake([
            'api.paystack.co/*' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'access_code' => 'test_access_code',
                    'reference' => 'PAY-TEST456',
                ],
            ]),
        ]);

        // Retry should create new payment (not duplicate prevention)
        $response = $this->actingAs($this->user)
            ->postJson('/api/wallet/fund', [
                'amount' => 50000,
                'payment_gateway' => 'paystack',
            ]);

        $response->assertStatus(202);

        // Should have created new payment
        $this->assertCount(2, PaymentTransaction::where('user_id', $this->user->id)->get());
    }
}
