<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;   // ✅ import the base Controller
use App\Services\AdvancedBudgetInsightsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvancedBudgetInsightsController extends Controller
{
    public function __construct(
        private readonly AdvancedBudgetInsightsService $budgetInsightsService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $insights = $this->budgetInsightsService->analyze(
            user: $request->user(),
        );

        return response()->json($insights);
    }
}
