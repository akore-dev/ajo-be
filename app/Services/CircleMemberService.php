<?php

namespace App\Services;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\User;
use App\Traits\HasTransactionalLogic;
use RuntimeException;

class CircleMemberService
{
    use HasTransactionalLogic;

    public function joinCircle(User $user, int $circleId, ?int $preferredPosition = null): CircleMember
    {
        return $this->executeTransaction(function () use ($user, $circleId, $preferredPosition) {
            $circle = Circle::where('id', $circleId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($circle->status !== 'recruiting') {
                throw new RuntimeException('This circle is no longer accepting new members.');
            }

            $currentMemberCount = CircleMember::where('circle_id', $circle->id)
                ->lockForUpdate()
                ->count();

            if ($currentMemberCount >= $circle->max_members) {
                throw new RuntimeException('This circle is already at full capacity.');
            }

            if (CircleMember::where('circle_id', $circle->id)->where('user_id', $user->id)->exists()) {
                throw new RuntimeException('You are already a member of this circle.');
            }

            $takenPositions = CircleMember::where('circle_id', $circle->id)
                ->pluck('payout_position')
                ->toArray();

            $position = $preferredPosition;

            if (! $position || in_array($position, $takenPositions) || $position > $circle->max_members) {
                for ($i = 1; $i <= $circle->max_members; $i++) {
                    if (! in_array($i, $takenPositions)) {
                        $position = $i;
                        break;
                    }
                }
            }

            if (! $position) {
                throw new RuntimeException('Unable to assign a payout position.');
            }

            $member = CircleMember::create([
                'circle_id' => $circle->id,
                'user_id' => $user->id,
                'role' => 'member',
                'payout_position' => $position,
                'payout_status' => 'pending',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $circle->increment('current_members');

            if (($currentMemberCount + 1) === $circle->max_members) {
                $circle->update(['status' => 'active']);
            }

            return $member;
        });
    }

    public function leaveCircle(User $user, int $circleId): bool
    {
        return $this->executeTransaction(function () use ($user, $circleId) {
            $circle = Circle::where('id', $circleId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($circle->status !== 'recruiting') {
                throw new RuntimeException('You cannot leave a circle that has already started.');
            }

            if ($circle->creator_id === $user->id) {
                throw new RuntimeException('As the creator, you cannot leave the circle. You must cancel it instead.');
            }

            $member = CircleMember::where('circle_id', $circle->id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $deleted = (bool) $member->delete();

            if ($deleted) {
                $circle->decrement('current_members');
            }

            return $deleted;
        });
    }
}

