<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circle_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['admin', 'member'])->default('member');
            $table->integer('payout_position');
            $table->enum('payout_status', ['pending', 'received'])->default('pending');
            $table->enum('status', ['active', 'inactive', 'left'])->default('active');
            $table->timestamp('joined_at')->useCurrent();
            $table->unique(['circle_id', 'user_id']);
            $table->unique(['circle_id', 'payout_position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_members');
    }
};

