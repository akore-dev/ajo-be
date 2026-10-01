<?php

namespace App\Services;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\PayoutRecord;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayoutService
{
    public function __construct(
        protected NotificationService $notificationService,
        protected WalletService $walletService
    ) {}

    public function schedulePayoutForCycle(int $circleId, int $cycle): ?PayoutRecord
    {
        $circle = Circle::find($circleId);

        if (! $circle || $circle->status !== 'active') {
            Log::warning("Payout scheduling ignored: Circle [#{$circleId}] not found or not active.");
            return null;
        }

        $existingPayout = PayoutRecord::where('circle_id', $circleId)
            ->where('cycle', $cycle)
            ->first();

        if ($existingPayout && in_array($existingPayout->status, ['completed', 'processing'])) {
            Log::info("Payout for Circle [#{$circleId}] Cycle {$cycle} is already {$existingPayout->status}.");
            return $existingPayout;
        }

        try {
            return $this->processCyclePayout($circle);
        } catch (Exception $e) {
            Log::error("Failed to execute scheduled payout for Circle [#{$circleId}] Cycle {$cycle}: " . $e->getMessage());

            return PayoutRecord::updateOrCreate(
                ['circle_id' => $circleId, 'cycle' => $cycle],
                [
                    'recipient_user_id' => $existingPayout?->recipient_user_id ?? $circle->creator_id,
                    'gross_amount' => $existingPayout?->gross_amount ?? 0,
                    'fee_amount' => $existingPayout?->fee_amount ?? 0,
                    'net_amount' => $existingPayout?->net_amount ?? 0,
                    'status' => 'failed',
                    'idempotency_key' => $existingPayout?->idempotency_key ?? 'PAYOUT-' . $circleId . '-' . $cycle,
                ]
            );
        }
    }

    public function processCyclePayout(Circle $circle): PayoutRecord
    {
        return DB::transaction(function () use ($circle) {
            $circle = Circle::where('id', $circle->id)->lockForUpdate()->firstOrFail();

            if ($circle->status !== 'active') {
                throw new Exception("Payout cannot be processed for non-active circle [#{$circle->id}].");
            }

            $currentCycle = $circle->current_cycle;

            $existingPayout = PayoutRecord::where('circle_id', $circle->id)
                ->where('cycle', $currentCycle)
                ->first();

            if ($existingPayout && $existingPayout->status === 'completed') {
                throw new Exception("Payout for cycle {$currentCycle} in circle '{$circle->name}' has already been processed.");
            }

            $recipientMember = CircleMember::where('circle_id', $circle->id)
                ->where('payout_position', $currentCycle)
                ->with('user')
                ->first();

            if (! $recipientMember) {
                throw new Exception("No member assigned to position #{$currentCycle} in circle '{$circle->name}'.");
            }

            $totalMembers = $circle->members()->count();
            $grossAmount = $circle->contribution_amount * $totalMembers;
            $feePercentage = config('finance.platform_payout_fee_percentage', 0);
            $feeAmount = (int) round(($grossAmount * $feePercentage) / 100);
            $netAmount = $grossAmount - $feeAmount;

            $payout = PayoutRecord::updateOrCreate(
                [
                    'circle_id' => $circle->id,
                    'cycle' => $currentCycle,
                ],
                [
                    'recipient_user_id' => $recipientMember->user_id,
                    'gross_amount' => $grossAmount,
                    'fee_amount' => $feeAmount,
                    'net_amount' => $netAmount,
                    'status' => 'processing',
                    'disbursed_at' => null,
                    'idempotency_key' => $existingPayout?->idempotency_key ?? 'PAYOUT-' . $circle->id . '-' . $currentCycle,
                ]
            );

            $disbursed = $this->disburseFunds($recipientMember->user, $netAmount, $payout);

            if (! $disbursed) {
                $payout->update(['status' => 'failed']);
                throw new Exception("Disbursement failed for User #{$recipientMember->user_id} in Cycle {$currentCycle}.");
            }

            $payout->update([
                'status' => 'completed',
                'disbursed_at' => now(),
            ]);

            $this->notificationService->sendPayoutDisbursedAlert(
                $recipientMember->user,
                $circle,
                $netAmount
            );

            $this->advanceCircleCycle($circle, $totalMembers);

            return $payout;
        });
    }

    protected function disburseFunds($user, int $amount, PayoutRecord $payout): bool
    {
        try {
            $this->walletService->credit(
                $user,
                $amount,
                'payout',
                ['source' => 'payout'],
                "Payout credited for circle {$payout->circle_id}",
                ['payout_id' => $payout->id]
            );

            Log::info("Disbursed NGN " . number_format($amount / 100, 2) . " to User #{$user->id} for Payout #{$payout->id}");

            return true;
        } catch (\Throwable $e) {
            Log::error('Disbursement Exception: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'payout_id' => $payout->id,
            ]);

            return false;
        }
    }

    protected function advanceCircleCycle(Circle $circle, int $totalMembers): void
    {
        if ($circle->current_cycle >= $totalMembers) {
            $circle->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        } else {
            $circle->increment('current_cycle');
        }
    }
}

