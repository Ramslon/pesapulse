<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdvancedGoalTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvancedGoalTrackingController extends Controller
{
    public function __construct(
        private readonly AdvancedGoalTrackingService $goalTrackingService,
    ) {
    }

    /**
     * Premium advanced goal tracking.
     */
    public function index(Request $request): JsonResponse
    {
        $goalId = $request->filled('goal_id')
            ? (int) $request->input('goal_id')
            : null;

        $tracking = $this->goalTrackingService->analyze(
            user: $request->user(),
            goalId: $goalId,
        );

        return response()->json($tracking);
    }
}