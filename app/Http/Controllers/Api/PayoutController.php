<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\ApiResponse;
use App\Models\Circle;
use App\Models\PayoutRecord;
use App\Services\PayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PayoutController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PayoutService $payoutService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $payouts = $request->user()
            ->payouts()
            ->with('circle:id,name,contribution_amount')
            ->latest()
            ->paginate(15);

        return $this->success($payouts, 'Payout history retrieved successfully.');
    }

    public function show(PayoutRecord $payout, Request $request): JsonResponse
    {
        if ($payout->recipient_user_id !== $request->user()->id && $payout->circle->creator_id !== $request->user()->id) {
            return $this->error('Unauthorized to view this payout.', Response::HTTP_FORBIDDEN);
        }

        return $this->success($payout->load(['circle', 'recipient:id,name,email']), 'Payout details retrieved.');
    }

    public function process(Request $request, Circle $circle): JsonResponse
    {
        if ($circle->creator_id !== $request->user()->id) {
            return $this->error('Only the circle creator can process payouts for this circle.', Response::HTTP_FORBIDDEN);
        }

        try {
            $payout = $this->payoutService->processCyclePayout($circle);

            return $this->success($payout, 'Payout processed and credited to member wallet successfully.', Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}

