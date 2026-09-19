<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

class SpendingForecastService
{
    public function forecast(
        User $user,
        int $months = 6,
        int $forecastMonths = 3
    ): array {
        $months = max(3, min($months, 12));
        $forecastMonths = max(1, min($forecastMonths, 6));

        $endDate = now()->endOfMonth();

        $startDate = now()
            ->copy()
            ->subMonths($months - 1)
            ->startOfMonth();

        /*
        |--------------------------------------------------------------------------
        | Historical expenses
        |--------------------------------------------------------------------------
        */

        $expenses = $user->expenses()
            ->whereBetween('expense_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->get([
                'amount',
                'category',
                'expense_date',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Monthly spending history
        |--------------------------------------------------------------------------
        */

        $monthly = [];

        for ($i = 0; $i < $months; $i++) {
            $date = $startDate->copy()->addMonths($i);

            $year = $date->year;
            $month = $date->month;

            $key = sprintf(
                '%04d-%02d',
                $year,
                $month
            );

            $monthExpenses = $expenses->filter(
                function ($expense) use ($year, $month) {
                    if (!$expense->expense_date) {
                        return false;
                    }

                    $expenseDate = Carbon::parse(
                        $expense->expense_date
                    );

                    return $expenseDate->year === $year &&
                        $expenseDate->month === $month;
                }
            );

            $spent = round(
                (float) $monthExpenses->sum('amount'),
                2
            );

            $monthly[] = [
                'month' => $key,
                'year' => $year,
                'month_number' => $month,
                'spent' => $spent,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Remove empty trailing periods only for calculations.
        | Historical timeline still contains every requested month.
        |--------------------------------------------------------------------------
        */

        $monthsWithSpending = array_values(
            array_filter(
                $monthly,
                fn ($month) => $month['spent'] > 0
            )
        );

        $spendingValues = array_map(
            fn ($month) => (float) $month['spent'],
            $monthsWithSpending
        );

        /*
        |--------------------------------------------------------------------------
        | Data quality
        |--------------------------------------------------------------------------
        */

        $monthsAvailable = count($monthly);
        $monthsWithSpendingCount = count(
            $monthsWithSpending
        );

        $canForecast =
            $monthsWithSpendingCount >= 2;

        /*
        |--------------------------------------------------------------------------
        | Historical summary
        |--------------------------------------------------------------------------
        */

        $totalHistoricalSpending = round(
            array_sum($spendingValues),
            2
        );

        $averageMonthlySpending =
    $monthsAvailable > 0
        ? round(
            $totalHistoricalSpending /
            $monthsAvailable,
            2
        )
        : 0.0;

        $averageActiveMonthSpending =
        $monthsWithSpendingCount > 0
        ? round(
            $totalHistoricalSpending /
            $monthsWithSpendingCount,
            2
        )
        : 0.0;

        /*
        |--------------------------------------------------------------------------
        | Linear trend
        |--------------------------------------------------------------------------
        |
        | Simple least-squares regression:
        |
        | y = a + bx
        |
        | x = chronological month index
        | y = monthly spending
        |
        */

        $slope = 0.0;
        $intercept = $averageMonthlySpending;

        if ($monthsWithSpendingCount >= 2) {
            $xValues = [];
            $yValues = [];

            foreach ($monthly as $index => $month) {
                if ($month['spent'] <= 0) {
                    continue;
                }

                $xValues[] = $index;
                $yValues[] = (float) $month['spent'];
            }

            $xMean =
                array_sum($xValues) /
                count($xValues);

            $yMean =
                array_sum($yValues) /
                count($yValues);

            $numerator = 0.0;
            $denominator = 0.0;

            foreach ($xValues as $index => $x) {
                $differenceX =
                    $x - $xMean;

                $differenceY =
                    $yValues[$index] - $yMean;

                $numerator +=
                    $differenceX *
                    $differenceY;

                $denominator +=
                    $differenceX *
                    $differenceX;
            }

            if ($denominator > 0) {
                $slope =
                    $numerator /
                    $denominator;
            }

            $intercept =
                $yMean -
                ($slope * $xMean);
        }

        /*
        |--------------------------------------------------------------------------
        | Trend classification
        |--------------------------------------------------------------------------
        */

        $trend = 'insufficient_data';

        if ($monthsWithSpendingCount >= 2) {
            $first =
                (float) $monthsWithSpending[0]['spent'];

            $last =
                (float) $monthsWithSpending[
                    $monthsWithSpendingCount - 1
                ]['spent'];

            if ($last > $first * 1.10) {
                $trend = 'increasing';
            } elseif ($last < $first * 0.90) {
                $trend = 'decreasing';
            } else {
                $trend = 'stable';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Forecast
        |--------------------------------------------------------------------------
        */

        $forecast = [];

        if ($canForecast) {
            $lastHistoricalIndex =
                count($monthly) - 1;

            for ($i = 1; $i <= $forecastMonths; $i++) {
                $forecastIndex =
                    $lastHistoricalIndex + $i;

                $forecastValue =
                    $intercept +
                    ($slope * $forecastIndex);

                /*
                | Never allow a negative spending forecast.
                */
                $forecastValue = max(
                    0,
                    round($forecastValue, 2)
                );

                $forecastDate = $endDate
                    ->copy()
                    ->addMonths($i);

                $forecast[] = [
                    'month' => $forecastDate
                        ->format('Y-m'),

                    'year' => $forecastDate->year,

                    'month_number' =>
                        $forecastDate->month,

                    'projected_spending' =>
                        $forecastValue,
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Forecast totals
        |--------------------------------------------------------------------------
        */

        $totalForecast =
            round(
                array_sum(
                    array_column(
                        $forecast,
                        'projected_spending'
                    )
                ),
                2
            );

        $averageForecast =
            count($forecast) > 0
                ? round(
                    $totalForecast /
                    count($forecast),
                    2
                )
                : 0.0;

        /*
        |--------------------------------------------------------------------------
        | Forecast confidence
        |--------------------------------------------------------------------------
        */

        $confidence =
            match (true) {
                $monthsWithSpendingCount >= 7 =>
                    'high',

                $monthsWithSpendingCount >= 4 =>
                    'medium',

                $monthsWithSpendingCount >= 2 =>
                    'low',

                default =>
                    'insufficient_data',
            };

        $confidenceMessage =
            match ($confidence) {
                'high' =>
                    'The forecast is based on at least seven months of spending history.',

                'medium' =>
                    'The forecast is based on several months of spending history and should be treated as an estimate.',

                'low' =>
                    'Only a small amount of spending history is available, so the forecast may change significantly.',

                default =>
                    'There is not enough historical spending data to produce a reliable forecast.',
            };

        /*
        |--------------------------------------------------------------------------
        | Highest forecast month
        |--------------------------------------------------------------------------
        */

        $highestForecast = null;

        if (!empty($forecast)) {
            $highestForecast =
                collect($forecast)
                    ->sortByDesc(
                        'projected_spending'
                    )
                    ->first();
        }

        /*
        |--------------------------------------------------------------------------
        | Category contribution
        |--------------------------------------------------------------------------
        */

        $categoryTotals = [];

        foreach ($expenses as $expense) {
            $category = trim(
                strtolower(
                    $expense->category ?? 'other'
                )
            );

            if ($category === '') {
                $category = 'other';
            }

            $categoryTotals[$category] =
                ($categoryTotals[$category] ?? 0)
                + (float) $expense->amount;
        }

        arsort($categoryTotals);

        $categoryBreakdown = [];

        foreach ($categoryTotals as $category => $amount) {
            $percentage =
                $totalHistoricalSpending > 0
                    ? round(
                        ($amount /
                            $totalHistoricalSpending) *
                        100,
                        1
                    )
                    : 0.0;

            $categoryBreakdown[] = [
                'category' =>
                    ucfirst($category),

                'historical_spending' =>
                    round($amount, 2),

                'percentage' =>
                    $percentage,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Recommendations
        |--------------------------------------------------------------------------
        */

        $recommendations = [];

        if (!$canForecast) {
            $recommendations[] = [
                'type' => 'data',
                'priority' => 'medium',
                'title' => 'Keep recording expenses',
                'message' =>
                    'Add more monthly spending history so PesaPulse can produce a more meaningful forecast.',
            ];
        }

        if (
            $canForecast &&
            $trend === 'increasing'
        ) {
            $recommendations[] = [
                'type' => 'trend',
                'priority' => 'high',
                'title' => 'Spending is trending upward',
                'message' =>
                    'Your historical spending shows an upward trend. Review recent spending categories before the trend continues.',
            ];
        }

        if (
            $canForecast &&
            $trend === 'decreasing'
        ) {
            $recommendations[] = [
                'type' => 'positive',
                'priority' => 'low',
                'title' => 'Spending is trending downward',
                'message' =>
                    'Your historical spending shows a downward trend. Continue tracking expenses to maintain the improvement.',
            ];
        }

        if (!empty($categoryBreakdown)) {
            $topCategory =
                $categoryBreakdown[0];

            $recommendations[] = [
                'type' => 'category',
                'priority' => 'medium',
                'title' =>
                    'Review your largest spending category',
                'message' =>
                    $topCategory['category']
                    . ' represents '
                    . $topCategory['percentage']
                    . '% of your historical spending.',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Final response
        |--------------------------------------------------------------------------
        */

        return [
            'period' => [
                'historical_months' =>
                    $months,

                'forecast_months' =>
                    $forecastMonths,

                'history_start' =>
                    $startDate->toDateString(),

                'history_end' =>
                    $endDate->toDateString(),
            ],

            'history' => [
                'monthly' =>
                    $monthly,

                'total_spending' =>
                    $totalHistoricalSpending,

                'average_monthly_spending' =>
                    $averageMonthlySpending,

                'average_active_month_spending' => 
                    $averageActiveMonthSpending,

                'months_available' =>
                    $monthsAvailable,

                'months_with_spending' =>
                    $monthsWithSpendingCount,
            ],

            'trend' => [
                'direction' =>
                    $trend,

                'slope' =>
                    round($slope, 2),
            ],

            'forecast' => [
                'monthly' =>
                    $forecast,

                'total_projected_spending' =>
                    $totalForecast,

                'average_projected_monthly_spending' =>
                    $averageForecast,

                'highest_projected_month' =>
                    $highestForecast,
            ],

            'categories' => [
                'breakdown' =>
                    $categoryBreakdown,
            ],

            'confidence' => [
                'level' =>
                    $confidence,

                'message' =>
                    $confidenceMessage,
            ],

            'recommendations' =>
                $recommendations,

            'data_quality' => [
                'months_available' =>
                    $monthsAvailable,

                'months_with_spending' =>
                    $monthsWithSpendingCount,

                'can_forecast' =>
                    $canForecast,

                'forecast_basis' =>
                    'Historical monthly spending using a linear trend model.',

                'current_month_is_partial' =>
                     now()->day < now()->daysInMonth,

                'partial_month_note' =>
                     now()->day < now()->daysInMonth
                         ? 'The current month is still in progress and contains partial spending data.'
                         : 'The latest historical month is complete.',
            ],
        ];
    }
}