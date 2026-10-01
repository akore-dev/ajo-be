<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Traits\HasTransactionalLogic;
use Illuminate\Support\Str;
use RuntimeException;

class WalletService
{
    use HasTransactionalLogic;

    public function getOrCreateWallet(User $user): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id],
            [
                'balance' => 0,
                'ledger_balance' => 0,
                'currency' => 'NGN',
                'status' => 'active',
            ]
        );
    }

    public function credit(
        User $user,
        int $amount,
        string $type,
        array $sourceDetails = [],
        ?string $description = null,
        array $metadata = []
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new RuntimeException('Credit amount must be greater than zero.');
        }

        return $this->executeTransaction(function () use ($user, $amount, $type, $sourceDetails, $description, $metadata) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

            if (! $wallet) {
                $wallet = $this->getOrCreateWallet($user);
                $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();
            }

            if ($wallet->status !== 'active') {
                throw new RuntimeException('Wallet is suspended or inactive.');
            }

            $balanceBefore = $wallet->balance;
            $balanceAfter = $balanceBefore + $amount;

            $wallet->update([
                'balance' => $balanceAfter,
                'ledger_balance' => $wallet->ledger_balance + $amount,
            ]);

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'reference' => 'TXN-' . strtoupper(Str::random(12)),
                'type' => $type,
                'direction' => 'credit',
                'source' => $sourceDetails['source'] ?? 'wallet',
                'bank_name' => $sourceDetails['bank_name'] ?? null,
                'account_number' => $sourceDetails['account_number'] ?? null,
                'channel_reference' => $sourceDetails['channel_reference'] ?? null,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description ?? ucfirst($type) . ' credited to wallet',
                'metadata' => $metadata,
                'status' => 'completed',
            ]);
        });
    }

    public function debit(
        User $user,
        int $amount,
        string $type,
        array $sourceDetails = [],
        ?string $description = null,
        array $metadata = []
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new RuntimeException('Debit amount must be greater than zero.');
        }

        return $this->executeTransaction(function () use ($user, $amount, $type, $sourceDetails, $description, $metadata) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            if ($wallet->status !== 'active') {
                throw new RuntimeException('Wallet is suspended or inactive.');
            }

            if ($wallet->balance < $amount) {
                throw new RuntimeException('Insufficient wallet balance.');
            }

            $balanceBefore = $wallet->balance;
            $balanceAfter = $balanceBefore - $amount;

            $wallet->update([
                'balance' => $balanceAfter,
                'ledger_balance' => $wallet->ledger_balance - $amount,
            ]);

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'reference' => 'TXN-' . strtoupper(Str::random(12)),
                'type' => $type,
                'direction' => 'debit',
                'source' => $sourceDetails['source'] ?? 'wallet',
                'bank_name' => $sourceDetails['bank_name'] ?? null,
                'account_number' => $sourceDetails['account_number'] ?? null,
                'channel_reference' => $sourceDetails['channel_reference'] ?? null,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description ?? ucfirst($type) . ' debited from wallet',
                'metadata' => $metadata,
                'status' => 'completed',
            ]);
        });
    }
}

