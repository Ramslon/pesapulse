<?php

namespace App\Http\Controllers;

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