<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\SpendingForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpendingForecastController extends Controller
{
    public function __construct(
        private readonly SpendingForecastService $forecastService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $months = (int) $request->input('months', 6);
        $forecastMonths =
            (int) $request->input('forecast_months', 3);

        $result = $this->forecastService->forecast(
            user: $request->user(),
            months: $months,
            forecastMonths: $forecastMonths,
        );

        return response()->json($result);
    }
}