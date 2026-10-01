<?php

namespace App\Services;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\User;
use App\Traits\HasTransactionalLogic;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use RuntimeException;

class CircleService
{
    use HasTransactionalLogic;

    public function createCircle(User $creator, array $data): Circle
    {
        return $this->executeTransaction(function () use ($creator, $data) {
            $circle = Circle::create([
                'creator_id' => $creator->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'contribution_amount' => $data['contribution_amount'],
                'currency' => $data['currency'] ?? 'NGN',
                'frequency' => $data['frequency'],
                'max_members' => $data['max_members'],
                'current_members' => 1,
                'draw_method' => $data['draw_method'],
                'status' => 'recruiting',
                'start_date' => $data['start_date'] ?? now()->addDays(7),
                'next_payout_date' => $data['start_date'] ?? now()->addDays(7),
                'current_cycle' => 1,
                'total_cycles' => $data['max_members'],
            ]);

            CircleMember::create([
                'circle_id' => $circle->id,
                'user_id' => $creator->id,
                'role' => 'admin',
                'payout_position' => 1,
                'payout_status' => 'pending',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            return $circle;
        });
    }

    public function cancelCircle(User $user, int $circleId): bool
    {
        return $this->executeTransaction(function () use ($user, $circleId) {
            $circle = Circle::where('id', $circleId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($circle->creator_id !== $user->id) {
                throw new RuntimeException('Only the circle creator can perform this action.');
            }

            if ($circle->status !== 'recruiting') {
                throw new RuntimeException('Cannot cancel a circle that is already active or completed.');
            }

            return $circle->update(['status' => 'cancelled']);
        });
    }

    public function getRecruitingCircles(int $perPage = 15): LengthAwarePaginator
    {
        return Circle::with(['creator', 'members.user'])
            ->where('status', 'recruiting')
            ->latest()
            ->paginate($perPage);
    }
}

