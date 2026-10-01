<?php

namespace App\Services;

use App\Models\CircleMember;
use App\Models\Contribution;
use App\Models\User;
use App\Traits\HasTransactionalLogic;
use Illuminate\Support\Str;
use RuntimeException;

class ContributionService
{
    use HasTransactionalLogic;

    public function initiateContribution(User $user, int $circleId, int $cycle, string $idempotencyKey): Contribution
    {
        return $this->executeTransaction(function () use ($user, $circleId, $cycle, $idempotencyKey) {
            $existing = Contribution::where('idempotency_key', $idempotencyKey)->first();

            if ($existing) {
                return $existing;
            }

            $member = CircleMember::where('circle_id', $circleId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $alreadyPaid = Contribution::where('circle_id', $circleId)
                ->where('member_id', $member->id)
                ->where('cycle', $cycle)
                ->where('status', 'paid')
                ->exists();

            if ($alreadyPaid) {
                throw new RuntimeException('Contribution for this cycle has already been completed.');
            }

            return Contribution::create([
                'circle_id' => $circleId,
                'member_id' => $member->id,
                'cycle' => $cycle,
                'amount' => $member->circle->contribution_amount,
                'due_date' => $member->circle->next_payout_date ?? now(),
                'status' => 'pending',
                'payment_reference' => 'CONT-' . strtoupper(Str::random(12)),
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }

    public function processWebhook(array $payload): void
    {
        $reference = $payload['data']['reference'] ?? null;
        if (! $reference) {
            return;
        }

        $this->executeTransaction(function () use ($reference, $payload) {
            $contribution = Contribution::where('payment_reference', $reference)
                ->lockForUpdate()
                ->first();

            if (! $contribution || $contribution->status === 'paid') {
                return;
            }

            if (($payload['event'] ?? '') === 'charge.success') {
                $contribution->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                $this->checkCycleCompletion($contribution->circle_id, $contribution->cycle);
            }
        });
    }

    protected function checkCycleCompletion(int $circleId, int $cycle): void
    {
        $totalMembers = CircleMember::where('circle_id', $circleId)->count();

        $paidMembersCount = Contribution::where('circle_id', $circleId)
            ->where('cycle', $cycle)
            ->where('status', 'paid')
            ->count();

        if ($totalMembers > 0 && $totalMembers === $paidMembersCount) {
            app(PayoutService::class)->schedulePayoutForCycle($circleId, $cycle);
        }
    }
}

