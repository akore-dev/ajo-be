<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circle_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained('circle_members')->restrictOnDelete();
            $table->integer('cycle');
            $table->bigInteger('amount');
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->enum('status', ['pending', 'paid', 'failed', 'overdue'])->default('pending');
            $table->string('payment_reference')->unique()->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamps();

            $table->unique(['circle_id', 'member_id', 'cycle']);
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contributions');
    }
};
