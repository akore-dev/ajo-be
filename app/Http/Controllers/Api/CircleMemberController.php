<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\ApiResponse;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CircleMemberController extends Controller
{
    use ApiResponse;

    public function index(Circle $circle): JsonResponse
    {
        $members = $circle->members()
            ->with('user:id,name,email')
            ->orderBy('payout_position', 'asc')
            ->get();

        return $this->success($members, 'Circle members retrieved successfully.');
    }

    public function updatePosition(Request $request, Circle $circle, User $user): JsonResponse
    {
        if ($circle->creator_id !== $request->user()->id) {
            return $this->error('Only the circle creator can update payout positions.', Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'payout_position' => ['required', 'integer', 'min:1', 'max:' . $circle->max_members],
        ]);

        $member = CircleMember::where('circle_id', $circle->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $member) {
            return $this->error('User is not a member of this circle.', Response::HTTP_NOT_FOUND);
        }

        $positionTaken = CircleMember::where('circle_id', $circle->id)
            ->where('payout_position', $validated['payout_position'])
            ->where('user_id', '!=', $user->id)
            ->exists();

        if ($positionTaken) {
            return $this->error('That payout position is already assigned to another member.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $member->update([
            'payout_position' => $validated['payout_position'],
        ]);

        return $this->success(null, 'Payout position updated successfully.');
    }

    public function destroy(Request $request, Circle $circle, User $user): JsonResponse
    {
        $authUserId = $request->user()->id;

        if ($authUserId !== $user->id && $circle->creator_id !== $authUserId) {
            return $this->error('You do not have permission to remove this member.', Response::HTTP_FORBIDDEN);
        }

        if (! $circle->members()->where('user_id', $user->id)->exists()) {
            return $this->error('User is not a member of this circle.', Response::HTTP_NOT_FOUND);
        }

        if ($circle->status === 'active') {
            return $this->error('Cannot leave or remove members from an active circle cycle.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($circle->creator_id === $user->id) {
            return $this->error('The creator cannot leave the circle. Cancel the circle instead.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $member = CircleMember::where('circle_id', $circle->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $member->delete();
        $circle->decrement('current_members');

        $message = $authUserId === $user->id ? 'You have left the circle.' : 'Member removed from circle successfully.';

        return $this->success(null, $message);
    }
}

