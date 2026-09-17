<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvancedAnalyticsController extends Controller
{
    /**
     * Premium historical and comparative financial analytics.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Determine requested period
        |--------------------------------------------------------------------------
        */

        $months = (int) $request->input('months', 6);

        $months = max(1, min($months, 12));

        $endDate = now()->endOfMonth();

        $startDate = now()
            ->copy()
            ->subMonths($months - 1)
            ->startOfMonth();

        /*
        |--------------------------------------------------------------------------
        | Load expenses once for the complete analysis period
        |--------------------------------------------------------------------------
        */

        $expenses = $user->expenses()
            ->whereBetween('expense_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->get([
                'category',
                'amount',
                'expense_date',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Load budgets once for the complete analysis period
        |--------------------------------------------------------------------------
        */

        $budgets = $user->budgets()
            ->where(function ($query) use ($startDate, $endDate) {
                $query
                    ->where('year', '>', $startDate->year)
                    ->orWhere(function ($query) use ($startDate) {
                        $query
                            ->where('year', $startDate->year)
                            ->where('month', '>=', $startDate->month);
                    });
            })
            ->where(function ($query) use ($endDate) {
                $query
                    ->where('year', '<', $endDate->year)
                    ->orWhere(function ($query) use ($endDate) {
                        $query
                            ->where('year', $endDate->year)
                            ->where('month', '<=', $endDate->month);
                    });
            })
            ->get([
                'amount',
                'month',
                'year',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Prepare monthly analytics
        |--------------------------------------------------------------------------
        */

        $monthly = [];

        for ($i = 0; $i < $months; $i++) {
            $monthDate = $startDate->copy()->addMonths($i);

            $month = $monthDate->month;
            $year = $monthDate->year;

            $monthKey = sprintf('%04d-%02d', $year, $month);

            $monthExpenses = $expenses->filter(function ($expense) use (
                $month,
                $year
            ) {
                if (!$expense->expense_date) {
                    return false;
                }

                $date = Carbon::parse($expense->expense_date);

                return $date->month === $month &&
                    $date->year === $year;
            });

            $spent = round(
                (float) $monthExpenses->sum('amount'),
                2
            );

            $budgetRecord = $budgets
                ->where('month', $month)
                ->where('year', $year)
                ->sortByDesc('id')
                ->first();

            $budget = $budgetRecord
                ? round((float) $budgetRecord->amount, 2)
                : 0;

            $remaining = round(
                $budget - $spent,
                2
            );

            $usagePercentage = $budget > 0
                ? round(($spent / $budget) * 100, 1)
                : 0;

            $categoryTotals = [];

            foreach ($monthExpenses as $expense) {
                $category = strtolower(
                    trim($expense->category ?? 'other')
                );

                $categoryTotals[$category] =
                    ($categoryTotals[$category] ?? 0) +
                    (float) $expense->amount;
            }

            arsort($categoryTotals);

            $topCategory = array_key_first(
                $categoryTotals
            );

            $monthly[$monthKey] = [
                'month' => $month,
                'year' => $year,
                'label' => $monthDate->format('M Y'),
                'budget' => $budget,
                'spent' => $spent,
                'remaining' => $remaining,
                'usage_percentage' => $usagePercentage,
                'expense_count' => $monthExpenses->count(),
                'top_category' => $topCategory,
                'top_category_amount' => $topCategory !== null
                    ? round(
                        (float) $categoryTotals[$topCategory],
                        2
                    )
                    : 0,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Overall period totals
        |--------------------------------------------------------------------------
        */

        $totalSpent = round(
            (float) $expenses->sum('amount'),
            2
        );

        $totalBudget = round(
            (float) collect($monthly)
                ->sum('budget'),
            2
        );

        $totalRemaining = round(
            $totalBudget - $totalSpent,
            2
        );

        $averageMonthlySpending = $months > 0
            ? round($totalSpent / $months, 2)
            : 0;

        /*
        |--------------------------------------------------------------------------
        | Previous-period comparison
        |--------------------------------------------------------------------------
        */

        $comparisonStartDate = $startDate
            ->copy()
            ->subMonths($months);

        $comparisonEndDate = $startDate
            ->copy()
            ->subDay();

        $previousExpenses = $user->expenses()
            ->whereBetween('expense_date', [
                $comparisonStartDate->toDateString(),
                $comparisonEndDate->toDateString(),
            ])
            ->get([
                'amount',
                'expense_date',
            ]);

        $previousTotalSpent = round(
            (float) $previousExpenses->sum('amount'),
            2
        );

        $spendingChange = round(
            $totalSpent - $previousTotalSpent,
            2
        );

        $spendingChangePercentage = $previousTotalSpent > 0
            ? round(
                ($spendingChange / $previousTotalSpent) * 100,
                2
            )
            : null;

        /*
        |--------------------------------------------------------------------------
        | Category analysis across the entire period
        |--------------------------------------------------------------------------
        */

        $categoryTotals = [];

        foreach ($expenses as $expense) {
            $category = strtolower(
                trim($expense->category ?? 'other')
            );

            $categoryTotals[$category] =
                ($categoryTotals[$category] ?? 0) +
                (float) $expense->amount;
        }

        arsort($categoryTotals);

        $categoryBreakdown = [];

        foreach ($categoryTotals as $category => $amount) {
            $amount = round((float) $amount, 2);

            $categoryBreakdown[] = [
                'category' => ucfirst($category),
                'amount' => $amount,
                'percentage' => $totalSpent > 0
                    ? round(
                        ($amount / $totalSpent) * 100,
                        2
                    )
                    : 0,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Spending consistency
        |--------------------------------------------------------------------------
        */

        $dailyTotals = [];

        foreach ($expenses as $expense) {
            if (!$expense->expense_date) {
                continue;
            }

            $date = Carbon::parse(
                $expense->expense_date
            )->toDateString();

            $dailyTotals[$date] =
                ($dailyTotals[$date] ?? 0) +
                (float) $expense->amount;
        }

        $dailyValues = array_values($dailyTotals);

$averageDailySpending = count($dailyValues) > 0
    ? $totalSpent / count($dailyValues)
    : 0;

$variance = 0;

if (count($dailyValues) > 0) {
    foreach ($dailyValues as $value) {
        $variance += pow(
            $value - $averageDailySpending,
            2
        );
    }

    $variance /= count($dailyValues);
}

$standardDeviation = sqrt($variance);

$consistencyRatio =
    $averageDailySpending > 0
        ? $standardDeviation / $averageDailySpending
        : 0;

$canCalculateConsistency = count($dailyValues) >= 2;

if (!$canCalculateConsistency) {
    $consistencyLabel = 'Insufficient Data';
} elseif ($consistencyRatio < 0.5) {
    $consistencyLabel = 'Consistent';
} elseif ($consistencyRatio < 1.0) {
    $consistencyLabel = 'Moderate Variability';
} else {
    $consistencyLabel = 'Highly Variable';
}

        /*
        |--------------------------------------------------------------------------
        | Highest spending day
        |--------------------------------------------------------------------------
        */

        $highestSpendingDay = null;

        if (!empty($dailyTotals)) {
            arsort($dailyTotals);

            $date = array_key_first($dailyTotals);

            $highestSpendingDay = [
                'date' => $date,
                'amount' => round(
                    (float) $dailyTotals[$date],
                    2
                ),
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Unusual spending detection
        |--------------------------------------------------------------------------
        */

        $anomalies = [];

        if ($standardDeviation > 0) {
            foreach ($dailyTotals as $date => $amount) {
                $zScore =
                    ($amount - $averageDailySpending) /
                    $standardDeviation;

                if ($zScore >= 2) {
                    $anomalies[] = [
                        'date' => $date,
                        'amount' => round(
                            (float) $amount,
                            2
                        ),
                        'severity' => $zScore >= 3
                            ? 'high'
                            : 'moderate',
                    ];
                }
            }
        }

        /*
|--------------------------------------------------------------------------
| Spending trend
|--------------------------------------------------------------------------
*/

$monthlySpent = array_values(
    collect($monthly)
        ->pluck('spent')
        ->all()
);

$monthsWithSpending = array_values(
    array_filter(
        $monthlySpent,
        fn ($amount) => (float) $amount > 0
    )
);

$canCalculateTrend = count($monthsWithSpending) >= 2;

$trend = 'insufficient_data';

if ($canCalculateTrend) {
    $first = (float) $monthsWithSpending[0];
    $last = (float) $monthsWithSpending[count($monthsWithSpending) - 1];

    if ($last > $first * 1.1) {
        $trend = 'increasing';
    } elseif ($last < $first * 0.9) {
        $trend = 'decreasing';
    } else {
        $trend = 'stable';
    }
}

        /*
|--------------------------------------------------------------------------
| Data quality / availability metadata
|--------------------------------------------------------------------------
|
| Explicitly tells the client whether derived insights can be
| meaningfully calculated instead of forcing Flutter to infer this.
|--------------------------------------------------------------------------
*/

$dataQuality = [
    'months_available' => $months,
    'months_with_spending' => count($monthsWithSpending),
    'spending_days' => count($dailyValues),
    'can_calculate_trend' => $canCalculateTrend,
    'can_calculate_consistency' => $canCalculateConsistency,
];

        /*
        |--------------------------------------------------------------------------
        | Final response
        |--------------------------------------------------------------------------
        */

        return response()->json([
    'period' => [
        'months' => $months,
        'start' => $startDate->toDateString(),
        'end' => $endDate->toDateString(),
    ],

    'data_quality' => $dataQuality,

    'summary' => [
        'total_spent' => $totalSpent,
        'total_budget' => $totalBudget,
        'remaining' => $totalRemaining,
        'average_monthly_spending' =>
            $averageMonthlySpending,
        'expense_count' => $expenses->count(),
    ],

    'comparison' => [
        'previous_period_spending' =>
            $previousTotalSpent,
        'change_amount' =>
            $spendingChange,
        'change_percentage' =>
            $spendingChangePercentage,
    ],

    'trend' => [
        'direction' => $trend,
        'monthly' => array_values($monthly),
    ],

    'categories' => [
        'breakdown' => $categoryBreakdown,
        'top_category' => !empty($categoryBreakdown)
            ? $categoryBreakdown[0]['category']
            : null,
    ],

    'spending_consistency' => [
        'average_daily_spending' =>
            round($averageDailySpending, 2),
        'standard_deviation' =>
            round($standardDeviation, 2),
        'label' => $consistencyLabel,
    ],

    'highest_spending_day' =>
        $highestSpendingDay,

    'anomalies' => $anomalies,
]);
    }
}