<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\ApiResponse;
use App\Http\Requests\JoinCircleRequest;
use App\Http\Requests\StoreCircleRequest;
use App\Models\Circle;
use App\Services\CircleMemberService;
use App\Services\CircleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class CircleController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CircleService $circleService,
        protected CircleMemberService $circleMemberService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $circles = Circle::withCount('members')
            ->when($request->boolean('my_circles'), function ($query) use ($request) {
                $query->whereHas('members', fn ($q) => $q->where('user_id', $request->user()->id));
            })
            ->latest()
            ->paginate(15);

        return $this->success($circles, 'Circles retrieved successfully.');
    }

    public function store(StoreCircleRequest $request): JsonResponse
    {
        $circle = $this->circleService->createCircle($request->user(), $request->validated());

        return $this->success(
            $circle->load(['creator', 'members.user']),
            'Circle created successfully.',
            Response::HTTP_CREATED
        );
    }

    public function show(Circle $circle): JsonResponse
    {
        return $this->success($circle->load(['creator', 'members.user', 'payoutRecords']), 'Circle details retrieved.');
    }

    public function join(JoinCircleRequest $request, Circle $circle): JsonResponse
    {
        try {
            $member = $this->circleMemberService->joinCircle(
                $request->user(),
                $circle->id,
                $request->validated('preferred_payout_position')
            );

            return $this->success($member->load('user'), 'Successfully joined circle.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}

