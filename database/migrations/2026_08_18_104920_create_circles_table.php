<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->bigInteger('contribution_amount');
            $table->string('currency', 3)->default('NGN');
            $table->enum('frequency', ['daily', 'weekly', 'monthly']);
            $table->integer('max_members');
            $table->integer('current_members')->default(1);
            $table->enum('draw_method', ['fixed', 'random', 'bidding']);
            $table->enum('status', ['draft', 'recruiting', 'active', 'completed'])->default('recruiting');
            $table->date('start_date')->nullable();
            $table->date('next_payout_date')->nullable();
            $table->integer('current_cycle')->default(1);
            $table->integer('total_cycles');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circles');
    }
};

