<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HistoricalInsightsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoricalInsightsController extends Controller
{
    public function __construct(
        private readonly HistoricalInsightsService $historicalInsightsService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'months' => [
                'nullable',
                'integer',
                'min:1',
                'max:24',
            ],
        ]);

        $months = isset($validated['months'])
            ? (int) $validated['months']
            : 12;

        $result = $this->historicalInsightsService->analyze(
            user: $request->user(),
            months: $months,
        );

        return response()->json($result);
    }
}