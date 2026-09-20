<?php

namespace App\Services;

use App\Models\User;

class BudgetSimulationService
{
    public function simulate(
        User $user,
        ?float $budgetAmount = null,
        float $spendingAdjustmentPercentage = 0,
    ): array {
        $today = now();

        $month = $today->month;
        $year = $today->year;

        $daysInMonth = $today->daysInMonth;
        $daysElapsed = max(1, $today->day);
        $daysRemaining = max(
            0,
            $daysInMonth - $today->day
        );

        /*
        |--------------------------------------------------------------------------
        | Current budget
        |--------------------------------------------------------------------------
        */

        $currentBudget = $user->budgets()
            ->where('month', $month)
            ->where('year', $year)
            ->latest()
            ->first();

        $currentBudgetAmount = $currentBudget
            ? (float) $currentBudget->amount
            : 0.0;

        /*
        |--------------------------------------------------------------------------
        | Simulated budget
        |--------------------------------------------------------------------------
        */

        $simulatedBudget = $budgetAmount !== null
            ? max(0, $budgetAmount)
            : $currentBudgetAmount;

        /*
        |--------------------------------------------------------------------------
        | Current-month expenses
        |--------------------------------------------------------------------------
        */

        $expenses = $user->expenses()
            ->whereMonth('expense_date', $month)
            ->whereYear('expense_date', $year)
            ->get([
                'amount',
                'category',
                'expense_date',
            ]);

        $spent = round(
            (float) $expenses->sum('amount'),
            2
        );

        /*
        |--------------------------------------------------------------------------
        | Actual spending pace
        |--------------------------------------------------------------------------
        */

        $actualDailySpending = $daysElapsed > 0
            ? round(
                $spent / $daysElapsed,
                2
            )
            : 0.0;

        /*
        |--------------------------------------------------------------------------
        | Remaining-day spending simulation
        |--------------------------------------------------------------------------
        |
        | Positive percentage = higher spending.
        | Negative percentage = lower spending.
        |
        */

        $spendingMultiplier =
            1 +
            ($spendingAdjustmentPercentage / 100);

        $spendingMultiplier = max(
            0,
            $spendingMultiplier
        );

        $simulatedDailySpending = round(
            $actualDailySpending *
            $spendingMultiplier,
            2
        );

        /*
        |--------------------------------------------------------------------------
        | Baseline projection
        |--------------------------------------------------------------------------
        */

        $baselineProjectedMonthEnd =
            round(
                $spent +
                ($actualDailySpending * $daysRemaining),
                2
            );

        /*
        |--------------------------------------------------------------------------
        | Simulated projection
        |--------------------------------------------------------------------------
        */

        $simulatedProjectedMonthEnd =
            round(
                $spent +
                ($simulatedDailySpending * $daysRemaining),
                2
            );

        $simulatedRemaining =
            round(
                $simulatedBudget -
                $simulatedProjectedMonthEnd,
                2
            );

        $baselineRemaining =
            round(
                $currentBudgetAmount -
                $baselineProjectedMonthEnd,
                2
            );

        /*
        |--------------------------------------------------------------------------
        | Usage
        |--------------------------------------------------------------------------
        */

        $simulatedUsagePercentage =
            $simulatedBudget > 0
                ? round(
                    (
                        $simulatedProjectedMonthEnd /
                        $simulatedBudget
                    ) * 100,
                    1
                )
                : null;

        $baselineUsagePercentage =
            $currentBudgetAmount > 0
                ? round(
                    (
                        $baselineProjectedMonthEnd /
                        $currentBudgetAmount
                    ) * 100,
                    1
                )
                : null;

        /*
        |--------------------------------------------------------------------------
        | Allowed daily spending
        |--------------------------------------------------------------------------
        */

        $allowedDailySpending = null;

        if (
            $simulatedBudget > 0 &&
            $daysRemaining > 0
        ) {
            $remainingAfterSpent =
                max(
                    0,
                    $simulatedBudget - $spent
                );

            $allowedDailySpending = round(
                $remainingAfterSpent /
                $daysRemaining,
                2
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        $status = $this->determineStatus(
            simulatedBudget: $simulatedBudget,
            projectedSpending:
                $simulatedProjectedMonthEnd,
            simulatedUsagePercentage:
                $simulatedUsagePercentage,
        );

        /*
        |--------------------------------------------------------------------------
        | Projection difference
        |--------------------------------------------------------------------------
        */

        $projectionChange =
            round(
                $simulatedProjectedMonthEnd -
                $baselineProjectedMonthEnd,
                2
            );

        $remainingChange =
            round(
                $simulatedRemaining -
                $baselineRemaining,
                2
            );

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return [
            'period' => [
                'month' => $month,
                'year' => $year,
                'days_in_month' => $daysInMonth,
                'days_elapsed' => $daysElapsed,
                'days_remaining' => $daysRemaining,
            ],

            'baseline' => [
                'budget' =>
                    round(
                        $currentBudgetAmount,
                        2
                    ),

                'spent' => $spent,

                'daily_spending' =>
                    $actualDailySpending,

                'projected_month_end_spending' =>
                    $baselineProjectedMonthEnd,

                'projected_remaining' =>
                    $baselineRemaining,

                'projected_usage_percentage' =>
                    $baselineUsagePercentage,
            ],

            'simulation' => [
                'budget' =>
                    round(
                        $simulatedBudget,
                        2
                    ),

                'spending_adjustment_percentage' =>
                    round(
                        $spendingAdjustmentPercentage,
                        2
                    ),

                'daily_spending' =>
                    $simulatedDailySpending,

                'projected_month_end_spending' =>
                    $simulatedProjectedMonthEnd,

                'projected_remaining' =>
                    $simulatedRemaining,

                'projected_usage_percentage' =>
                    $simulatedUsagePercentage,

                'allowed_daily_spending' =>
                    $allowedDailySpending,

                'status' => $status,
            ],

            'impact' => [
                'projected_spending_change' =>
                    $projectionChange,

                'projected_remaining_change' =>
                    $remainingChange,
            ],

            'data_quality' => [
                'has_budget' =>
                    $currentBudget !== null,

                'has_expenses' =>
                    $expenses->isNotEmpty(),

                'spending_days' =>
                    $expenses
                        ->pluck('expense_date')
                        ->filter()
                        ->unique()
                        ->count(),

                'simulation_basis' =>
                    'Current-month actual spending is preserved. The spending adjustment is applied only to the remaining days of the current month.',

                'is_current_month_partial' =>
                    $today->day < $daysInMonth,
            ],
        ];
    }

    private function determineStatus(
        float $simulatedBudget,
        float $projectedSpending,
        ?float $simulatedUsagePercentage,
    ): string {
        if ($simulatedBudget <= 0) {
            return 'no_budget';
        }

        if ($projectedSpending > $simulatedBudget) {
            return 'projected_over_budget';
        }

        if (
            $simulatedUsagePercentage !== null &&
            $simulatedUsagePercentage >= 90
        ) {
            return 'near_limit';
        }

        return 'under_budget';
    }
}