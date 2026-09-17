<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdvancedAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvancedAnalyticsController extends Controller
{
    public function __construct(
        private readonly AdvancedAnalyticsService $analyticsService,
    ) {
    }

    /**
     * Premium historical and comparative financial analytics.
     */
    public function index(Request $request): JsonResponse
    {
        $months = (int) $request->input('months', 6);

        $analytics = $this->analyticsService->analyze(
            user: $request->user(),
            months: $months,
        );

        return response()->json($analytics);
    }
}