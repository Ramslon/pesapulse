<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AdvancedAnalyticsService
{
    /**
     * Generate premium historical and comparative analytics.
     */
    public function analyze(
        User $user,
        int $months = 6
    ): array {
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
                            ->where(
                                'month',
                                '>=',
                                $startDate->month
                            );
                    });
            })
            ->where(function ($query) use ($endDate) {
                $query
                    ->where('year', '<', $endDate->year)
                    ->orWhere(function ($query) use ($endDate) {
                        $query
                            ->where('year', $endDate->year)
                            ->where(
                                'month',
                                '<=',
                                $endDate->month
                            );
                    });
            })
            ->orderByDesc('id')
            ->get([
                'id',
                'amount',
                'month',
                'year',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Monthly analytics
        |--------------------------------------------------------------------------
        */

        $monthly = $this->buildMonthlyAnalytics(
            expenses: $expenses,
            budgets: $budgets,
            startDate: $startDate,
            months: $months,
        );

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
            (float) collect($monthly)->sum('budget'),
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

        $comparison = $this->buildComparison(
            user: $user,
            startDate: $startDate,
            months: $months,
            totalSpent: $totalSpent,
        );

        /*
        |--------------------------------------------------------------------------
        | Category analysis
        |--------------------------------------------------------------------------
        */

        $categoryBreakdown = $this->buildCategoryBreakdown(
            expenses: $expenses,
            totalSpent: $totalSpent,
        );

        /*
        |--------------------------------------------------------------------------
        | Daily spending analysis
        |--------------------------------------------------------------------------
        */

        $dailyTotals = $this->buildDailyTotals($expenses);

        $dailyValues = array_values($dailyTotals);

        $averageDailySpending = count($dailyValues) > 0
            ? $totalSpent / count($dailyValues)
            : 0;

        /*
        |--------------------------------------------------------------------------
        | Spending consistency
        |--------------------------------------------------------------------------
        */

        $consistency = $this->buildSpendingConsistency(
            dailyValues: $dailyValues,
            averageDailySpending: $averageDailySpending,
        );

        /*
        |--------------------------------------------------------------------------
        | Highest spending day
        |--------------------------------------------------------------------------
        */

        $highestSpendingDay = $this->findHighestSpendingDay(
            $dailyTotals
        );

        /*
        |--------------------------------------------------------------------------
        | Unusual spending detection
        |--------------------------------------------------------------------------
        */

        $anomalies = $this->detectAnomalies(
            dailyTotals: $dailyTotals,
            averageDailySpending: $averageDailySpending,
            standardDeviation: $consistency['standard_deviation'],
        );

        /*
        |--------------------------------------------------------------------------
        | Spending trend
        |--------------------------------------------------------------------------
        */

        $trend = $this->buildSpendingTrend(
            monthly: $monthly
        );

        /*
        |--------------------------------------------------------------------------
        | Data quality
        |--------------------------------------------------------------------------
        */

        $dataQuality = [
            'months_available' => $months,
            'months_with_spending' => $trend['months_with_spending'],
            'spending_days' => count($dailyValues),
            'can_calculate_trend' => $trend['can_calculate'],
            'can_calculate_consistency' =>
                $consistency['can_calculate'],
        ];

        /*
        |--------------------------------------------------------------------------
        | Final analytics payload
        |--------------------------------------------------------------------------
        */

        return [
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

            'comparison' => $comparison,

            'trend' => [
                'direction' => $trend['direction'],
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
                    $consistency['standard_deviation'],
                'label' => $consistency['label'],
            ],

            'highest_spending_day' => $highestSpendingDay,

            'anomalies' => $anomalies,
        ];
    }

    /**
     * Build the monthly analytics collection.
     */
    private function buildMonthlyAnalytics(
        Collection $expenses,
        Collection $budgets,
        Carbon $startDate,
        int $months,
    ): array {
        $monthly = [];

        for ($i = 0; $i < $months; $i++) {
            $monthDate = $startDate->copy()->addMonths($i);

            $month = $monthDate->month;
            $year = $monthDate->year;

            $monthKey = sprintf(
                '%04d-%02d',
                $year,
                $month
            );

            $monthExpenses = $expenses->filter(
                function ($expense) use ($month, $year) {
                    if (!$expense->expense_date) {
                        return false;
                    }

                    $date = Carbon::parse(
                        $expense->expense_date
                    );

                    return $date->month === $month &&
                        $date->year === $year;
                }
            );

            $spent = round(
                (float) $monthExpenses->sum('amount'),
                2
            );

            $budgetRecord = $budgets
                ->where('month', $month)
                ->where('year', $year)
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
                'usage_percentage' =>
                    $usagePercentage,
                'expense_count' =>
                    $monthExpenses->count(),
                'top_category' =>
                    $topCategory,
                'top_category_amount' =>
                    $topCategory !== null
                        ? round(
                            (float) $categoryTotals[$topCategory],
                            2
                        )
                        : 0,
            ];
        }

        return $monthly;
    }

    /**
     * Compare the selected period against the previous period
     * of the same length.
     */
    private function buildComparison(
        User $user,
        Carbon $startDate,
        int $months,
        float $totalSpent,
    ): array {
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

        return [
            'previous_period_spending' =>
                $previousTotalSpent,
            'change_amount' =>
                $spendingChange,
            'change_percentage' =>
                $spendingChangePercentage,
        ];
    }

    /**
     * Build category totals and percentages.
     */
    private function buildCategoryBreakdown(
        Collection $expenses,
        float $totalSpent,
    ): array {
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

        return $categoryBreakdown;
    }

    /**
     * Build daily spending totals.
     */
    private function buildDailyTotals(
        Collection $expenses,
    ): array {
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

        return $dailyTotals;
    }

    /**
     * Calculate spending consistency.
     */
    private function buildSpendingConsistency(
        array $dailyValues,
        float $averageDailySpending,
    ): array {
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
                ? $standardDeviation /
                    $averageDailySpending
                : 0;

        $canCalculate = count($dailyValues) >= 2;

        if (!$canCalculate) {
            $label = 'Insufficient Data';
        } elseif ($consistencyRatio < 0.5) {
            $label = 'Consistent';
        } elseif ($consistencyRatio < 1.0) {
            $label = 'Moderate Variability';
        } else {
            $label = 'Highly Variable';
        }

        return [
            'standard_deviation' =>
                round($standardDeviation, 2),
            'label' => $label,
            'can_calculate' => $canCalculate,
        ];
    }

    /**
     * Find the highest spending day.
     */
    private function findHighestSpendingDay(
        array $dailyTotals,
    ): ?array {
        if (empty($dailyTotals)) {
            return null;
        }

        arsort($dailyTotals);

        $date = array_key_first($dailyTotals);

        return [
            'date' => $date,
            'amount' => round(
                (float) $dailyTotals[$date],
                2
            ),
        ];
    }

    /**
     * Detect unusually high spending days.
     */
    private function detectAnomalies(
        array $dailyTotals,
        float $averageDailySpending,
        float $standardDeviation,
    ): array {
        $anomalies = [];

        if ($standardDeviation <= 0) {
            return $anomalies;
        }

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

        return $anomalies;
    }

    /**
     * Calculate monthly spending trend.
     */
    private function buildSpendingTrend(
        array $monthly,
    ): array {
        $monthlySpent = array_values(
            collect($monthly)
                ->pluck('spent')
                ->all()
        );

        $monthsWithSpending = array_values(
            array_filter(
                $monthlySpent,
                fn ($amount) =>
                    (float) $amount > 0
            )
        );

        $canCalculate =
            count($monthsWithSpending) >= 2;

        $trend = 'insufficient_data';

        if ($canCalculate) {
            $first = (float) $monthsWithSpending[0];

            $last = (float) $monthsWithSpending[
                count($monthsWithSpending) - 1
            ];

            if ($last > $first * 1.1) {
                $trend = 'increasing';
            } elseif ($last < $first * 0.9) {
                $trend = 'decreasing';
            } else {
                $trend = 'stable';
            }
        }

        return [
            'direction' => $trend,
            'months_with_spending' =>
                count($monthsWithSpending),
            'can_calculate' => $canCalculate,
        ];
    }
}