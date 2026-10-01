<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circle_id')->constrained()->restrictOnDelete();
            $table->integer('cycle');
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->bigInteger('gross_amount');
            $table->bigInteger('fee_amount')->default(0);
            $table->bigInteger('net_amount');
            $table->timestamp('disbursed_at')->nullable();
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->string('idempotency_key')->unique();
            $table->timestamps();

            $table->unique(['circle_id', 'cycle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_records');
    }
};

