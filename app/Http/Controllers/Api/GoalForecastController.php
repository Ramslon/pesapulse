<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoalForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoalForecastController extends Controller
{
    public function __construct(
        private readonly GoalForecastService $forecastService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'goal_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ]);

        $result = $this->forecastService->forecast(
            user: $request->user(),
            goalId: isset($validated['goal_id'])
                ? (int) $validated['goal_id']
                : null,
        );

        return response()->json($result);
    }
}