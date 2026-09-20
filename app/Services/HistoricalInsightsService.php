<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

class HistoricalInsightsService
{
    public function analyze(
        User $user,
        int $months = 12,
    ): array {
        $months = max(1, min($months, 24));

        $today = now();

        $currentMonthStart = $today->copy()->startOfMonth();

        $startDate = $currentMonthStart
            ->copy()
            ->subMonths($months - 1)
            ->startOfMonth();

        $endDate = $currentMonthStart
            ->copy()
            ->endOfMonth();

        /*
        |--------------------------------------------------------------------------
        | Expenses
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
        | Budgets
        |--------------------------------------------------------------------------
        */

        $budgets = $user->budgets()
            ->whereBetween(
                'year',
                [$startDate->year, $endDate->year]
            )
            ->get([
                'amount',
                'month',
                'year',
            ])
            ->filter(function ($budget) use ($startDate, $endDate) {
                $date = Carbon::create(
                    (int) $budget->year,
                    (int) $budget->month,
                    1,
                );

                return $date->betweenIncluded(
                    $startDate->copy()->startOfMonth(),
                    $endDate->copy()->startOfMonth(),
                );
            });

        /*
        |--------------------------------------------------------------------------
        | Monthly buckets
        |--------------------------------------------------------------------------
        */

        $monthly = [];

        $cursor = $startDate->copy();

        while ($cursor->lte($currentMonthStart)) {
            $key = $cursor->format('Y-m');

            $monthly[$key] = [
                'month' => $key,
                'year' => $cursor->year,
                'month_number' => $cursor->month,
                'spent' => 0.0,
                'budget' => 0.0,
                'remaining' => 0.0,
                'usage_percentage' => null,
                'expense_count' => 0,
            ];

            $cursor->addMonth();
        }

        /*
        |--------------------------------------------------------------------------
        | Populate expenses
        |--------------------------------------------------------------------------
        */

        $categoryTotals = [];

        foreach ($expenses as $expense) {
            $date = Carbon::parse($expense->expense_date);

            $key = $date->format('Y-m');

            if (!isset($monthly[$key])) {
                continue;
            }

            $amount = (float) $expense->amount;

            $monthly[$key]['spent'] += $amount;
            $monthly[$key]['expense_count']++;

            $category = trim(
                $expense->category ?: 'Other'
            );

            $categoryTotals[$category] =
                ($categoryTotals[$category] ?? 0)
                + $amount;
        }

        /*
        |--------------------------------------------------------------------------
        | Populate budgets
        |--------------------------------------------------------------------------
        */

        foreach ($budgets as $budget) {
            $key = sprintf(
                '%04d-%02d',
                $budget->year,
                $budget->month,
            );

            if (!isset($monthly[$key])) {
                continue;
            }

            $monthly[$key]['budget'] +=
                (float) $budget->amount;
        }

        /*
        |--------------------------------------------------------------------------
        | Finalize monthly values
        |--------------------------------------------------------------------------
        */

        foreach ($monthly as &$data) {
            $data['spent'] = round(
                $data['spent'],
                2
            );

            $data['budget'] = round(
                $data['budget'],
                2
            );

            $data['remaining'] = round(
                $data['budget'] - $data['spent'],
                2
            );

            $data['usage_percentage'] =
                $data['budget'] > 0
                    ? round(
                        ($data['spent'] / $data['budget'])
                        * 100,
                        1,
                    )
                    : null;
        }

        unset($data);

        $monthlyList = array_values($monthly);

        /*
        |--------------------------------------------------------------------------
        | Summary
        |--------------------------------------------------------------------------
        */

        $totalSpending = round(
            collect($monthlyList)->sum('spent'),
            2,
        );

        $totalBudgeted = round(
            collect($monthlyList)->sum('budget'),
            2,
        );

        $monthsWithSpending = collect($monthlyList)
            ->filter(fn ($month) => $month['spent'] > 0)
            ->count();

        $monthsWithBudget = collect($monthlyList)
            ->filter(fn ($month) => $month['budget'] > 0)
            ->count();

        $averageMonthlySpending =
            $months > 0
                ? round(
                    $totalSpending / $months,
                    2,
                )
                : 0.0;

        $averageActiveMonthSpending =
            $monthsWithSpending > 0
                ? round(
                    $totalSpending / $monthsWithSpending,
                    2,
                )
                : 0.0;

        $highestMonth = collect($monthlyList)
            ->sortByDesc('spent')
            ->first();

        $lowestSpendingMonth = collect($monthlyList)
            ->filter(fn ($month) => $month['spent'] > 0)
            ->sortBy('spent')
            ->first();

        $budgetUsagePercentage =
            $totalBudgeted > 0
                ? round(
                    ($totalSpending / $totalBudgeted) * 100,
                    1,
                )
                : null;

        /*
        |--------------------------------------------------------------------------
        | Historical trend
        |--------------------------------------------------------------------------
        */

        $trend = $this->calculateTrend(
            $monthlyList
        );

        /*
        |--------------------------------------------------------------------------
        | Categories
        |--------------------------------------------------------------------------
        */

        arsort($categoryTotals);

        $categoryBreakdown = [];

        foreach ($categoryTotals as $category => $amount) {
            $categoryBreakdown[] = [
                'category' => $category,
                'amount' => round($amount, 2),
                'percentage' => $totalSpending > 0
                    ? round(
                        ($amount / $totalSpending) * 100,
                        1,
                    )
                    : 0.0,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Historical observations
        |--------------------------------------------------------------------------
        */

        $historicalInsights =
            $this->generateInsights(
                monthly: $monthlyList,
                trend: $trend,
                totalSpending: $totalSpending,
                totalBudgeted: $totalBudgeted,
                monthsWithSpending: $monthsWithSpending,
                highestMonth: $highestMonth,
                topCategory:
                    $categoryBreakdown[0]['category']
                    ?? null,
            );

        /*
        |--------------------------------------------------------------------------
        | Data quality
        |--------------------------------------------------------------------------
        */

        $isCurrentMonthPartial =
            $today->day < $today->daysInMonth;

        return [
            'period' => [
                'months' => $months,
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],

            'summary' => [
                'total_spending' => $totalSpending,
                'average_monthly_spending' =>
                    $averageMonthlySpending,
                'average_active_month_spending' =>
                    $averageActiveMonthSpending,

                'highest_spending_month' =>
                    $highestMonth
                        ? [
                            'month' => $highestMonth['month'],
                            'amount' => $highestMonth['spent'],
                        ]
                        : null,

                'lowest_spending_month' =>
                    $lowestSpendingMonth
                        ? [
                            'month' =>
                                $lowestSpendingMonth['month'],
                            'amount' =>
                                $lowestSpendingMonth['spent'],
                        ]
                        : null,

                'months_with_spending' =>
                    $monthsWithSpending,

                'total_budgeted' =>
                    $totalBudgeted,

                'average_monthly_budget' =>
                    $months > 0
                        ? round(
                            $totalBudgeted / $months,
                            2,
                        )
                        : 0.0,

                'budget_usage_percentage' =>
                    $budgetUsagePercentage,
            ],

            'monthly' => $monthlyList,

            'trend' => $trend,

            'categories' => [
                'top_category' =>
                    $categoryBreakdown[0]['category']
                    ?? null,

                'breakdown' =>
                    $categoryBreakdown,
            ],

            'historical_insights' =>
                $historicalInsights,

            'data_quality' => [
                'months_available' => $months,
                'months_with_spending' =>
                    $monthsWithSpending,
                'months_with_budget' =>
                    $monthsWithBudget,

                'can_analyze_trend' =>
                    $monthsWithSpending >= 2,

                'is_current_month_partial' =>
                    $isCurrentMonthPartial,
            ],
        ];
    }

    private function calculateTrend(
        array $monthly,
    ): array {
        $monthsWithSpending = array_values(
            array_filter(
                $monthly,
                fn ($month) => $month['spent'] > 0
            )
        );

        if (count($monthsWithSpending) < 2) {
            return [
                'direction' => 'insufficient_data',
                'change_amount' => null,
                'change_percentage' => null,
            ];
        }

        $first = $monthsWithSpending[0]['spent'];

        $last =
            $monthsWithSpending[
                count($monthsWithSpending) - 1
            ]['spent'];

        $changeAmount = round(
            $last - $first,
            2,
        );

        $changePercentage =
            $first > 0
                ? round(
                    ($changeAmount / $first) * 100,
                    1,
                )
                : null;

        if ($changeAmount > 0) {
            $direction = 'increasing';
        } elseif ($changeAmount < 0) {
            $direction = 'decreasing';
        } else {
            $direction = 'stable';
        }

        return [
            'direction' => $direction,
            'change_amount' => $changeAmount,
            'change_percentage' => $changePercentage,
        ];
    }

    private function generateInsights(
        array $monthly,
        array $trend,
        float $totalSpending,
        float $totalBudgeted,
        int $monthsWithSpending,
        ?array $highestMonth,
        ?string $topCategory,
    ): array {
        $insights = [];

        if (
            $trend['direction'] === 'increasing'
            && $trend['change_percentage'] !== null
            && $trend['change_percentage'] >= 10
        ) {
            $insights[] = [
                'type' => 'trend',
                'priority' => 'medium',
                'title' => 'Spending has increased',
                'message' =>
                    'Your spending increased compared with the beginning of the selected historical period.',
            ];
        }

        if (
            $trend['direction'] === 'decreasing'
            && $trend['change_percentage'] !== null
            && $trend['change_percentage'] <= -10
        ) {
            $insights[] = [
                'type' => 'trend',
                'priority' => 'low',
                'title' => 'Spending has decreased',
                'message' =>
                    'Your spending decreased compared with the beginning of the selected historical period.',
            ];
        }

        if (
            $totalBudgeted > 0
            && $totalSpending > $totalBudgeted
        ) {
            $insights[] = [
                'type' => 'budget',
                'priority' => 'medium',
                'title' => 'Historical spending exceeded budgets',
                'message' =>
                    'Your total spending exceeded the combined budgets across the selected historical period.',
            ];
        }

        if ($highestMonth !== null) {
            $insights[] = [
                'type' => 'month',
                'priority' => 'low',
                'title' => 'Highest spending month',
                'message' =>
                    sprintf(
                        '%s was your highest spending month at KES %s.',
                        $highestMonth['month'],
                        number_format(
                            $highestMonth['spent'],
                            2,
                        ),
                    ),
            ];
        }

        if ($topCategory !== null) {
            $insights[] = [
                'type' => 'category',
                'priority' => 'low',
                'title' => 'Largest historical category',
                'message' =>
                    "{$topCategory} represents your largest spending category in the selected historical period.",
            ];
        }

        if ($monthsWithSpending < 2) {
            $insights[] = [
                'type' => 'data',
                'priority' => 'medium',
                'title' => 'More history is needed',
                'message' =>
                    'Continue recording expenses across multiple months so PesaPulse can identify meaningful historical patterns.',
            ];
        }

        return $insights;
    }
}