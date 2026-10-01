<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('wallet_id')->nullable()->constrained('wallets')->onDelete('set null');

            $table->string('reference')->unique()->index();
            $table->string('authorization_code')->nullable()->index();
            $table->string('access_code')->nullable();
            $table->string('checkout_url')->nullable();

            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'abandoned'])->default('pending')->index();
            $table->string('gateway')->default('paystack');

            $table->bigInteger('amount');
            $table->string('currency')->default('NGN');

            $table->string('email')->nullable();
            $table->string('phone_number')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('initiated_at')->useCurrent();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->string('failure_reason')->nullable();
            $table->json('gateway_response')->nullable();

            $table->string('idempotency_key')->nullable()->unique()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['reference', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
