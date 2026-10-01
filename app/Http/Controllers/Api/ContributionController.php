<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\ApiResponse;
use App\Http\Requests\MakeContributionRequest;
use App\Models\Circle;
use App\Services\ContributionService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ContributionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ContributionService $contributionService,
        protected WalletService $walletService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $contributions = $request->user()
            ->contributions()
            ->with('circle:id,name,contribution_amount')
            ->latest()
            ->paginate(15);

        return $this->success($contributions, 'Contribution history retrieved.');
    }

    public function store(MakeContributionRequest $request): JsonResponse
    {
        $circle = Circle::findOrFail($request->validated('circle_id'));

        if (! $circle->members()->where('user_id', $request->user()->id)->exists()) {
            return $this->error('You must be a member of this circle to contribute.', Response::HTTP_FORBIDDEN);
        }

        $cycle = (int) $request->validated('cycle');
        $idempotencyKey = $request->validated('idempotency_key') ?: (string) Str::uuid();

        $contribution = $this->contributionService->initiateContribution(
            $request->user(),
            $circle->id,
            $cycle,
            $idempotencyKey
        );

        if ($request->validated('payment_method') === 'wallet') {
            try {
                $this->walletService->debit(
                    $request->user(),
                    $contribution->amount,
                    'contribution',
                    ['source' => 'wallet'],
                    "Contribution payment for circle {$circle->name}, cycle {$cycle}",
                    ['circle_id' => $circle->id, 'cycle' => $cycle]
                );

                $contribution->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            } catch (RuntimeException $e) {
                $contribution->update(['status' => 'failed']);

                return $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        return $this->success($contribution->fresh(), 'Contribution processed successfully.', Response::HTTP_CREATED);
    }
}

