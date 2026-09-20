<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BudgetSimulationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetSimulationController extends Controller
{
    public function __construct(
        private readonly BudgetSimulationService $simulationService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'budget_amount' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.99',
            ],

            'spending_adjustment_percentage' => [
                'nullable',
                'numeric',
                'min:-100',
                'max:200',
            ],
        ]);

        $result = $this->simulationService->simulate(
            user: $request->user(),

            budgetAmount:
                array_key_exists(
                    'budget_amount',
                    $validated
                )
                    ? (float) $validated['budget_amount']
                    : null,

            spendingAdjustmentPercentage:
                isset(
                    $validated[
                        'spending_adjustment_percentage'
                    ]
                )
                    ? (float) $validated[
                        'spending_adjustment_percentage'
                    ]
                    : 0,
        );

        return response()->json($result);
    }
}