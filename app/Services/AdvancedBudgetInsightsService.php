<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

class AdvancedBudgetInsightsService
{
    public function analyze(User $user): array
    {
        $today = now();

        $month = $today->month;
        $year = $today->year;

        $daysInMonth = $today->daysInMonth;
        $daysElapsed = max(1, $today->day);
        $daysRemaining = max(0, $daysInMonth - $today->day);

        /*
        |--------------------------------------------------------------------------
        | Budget
        |--------------------------------------------------------------------------
        */

        $budget = $user->budgets()
            ->where('month', $month)
            ->where('year', $year)
            ->latest()
            ->first();

        $hasBudget = $budget !== null;

        $budgetAmount = $hasBudget
            ? (float) $budget->amount
            : 0.0;

        /*
        |--------------------------------------------------------------------------
        | Current-month expenses
        |--------------------------------------------------------------------------
        */

        $expenses = $user->expenses()
            ->whereMonth('expense_date', $month)
            ->whereYear('expense_date', $year)
            ->get([
                'category',
                'amount',
                'expense_date',
            ]);

        $hasExpenses = $expenses->isNotEmpty();

        /*
        |--------------------------------------------------------------------------
        | Basic calculations
        |--------------------------------------------------------------------------
        */

        $spent = round(
            (float) $expenses->sum('amount'),
            2
        );

        $remaining = round(
            $budgetAmount - $spent,
            2
        );

        $usagePercentage = $budgetAmount > 0
            ? round(($spent / $budgetAmount) * 100, 1)
            : 0.0;

        /*
        |--------------------------------------------------------------------------
        | Expected budget usage based on time elapsed
        |--------------------------------------------------------------------------
        |
        | Example:
        |
        | 10th day of a 30-day month
        | Expected usage = 33.3%
        |
        */

        $expectedUsagePercentage = round(
            ($daysElapsed / $daysInMonth) * 100,
            1
        );

        /*
        |--------------------------------------------------------------------------
        | Daily spending pace
        |--------------------------------------------------------------------------
        */

        $actualDailySpending = $daysElapsed > 0
            ? round($spent / $daysElapsed, 2)
            : 0.0;

        $allowedDailySpending = null;

        if ($hasBudget && $daysRemaining > 0) {
            $allowedDailySpending = round(
                max(0, $remaining) / $daysRemaining,
                2
            );
        }

        /*
|--------------------------------------------------------------------------
| Pace difference
|--------------------------------------------------------------------------
|
| Positive = spending more of the budget than expected at this point.
| Negative = spending less of the budget than expected at this point.
|
*/

        $paceDifference = round(
            $usagePercentage - $expectedUsagePercentage,
            1
        );

        $paceStatus = 'no_data';

        if ($hasBudget && $hasExpenses) {
            if ($usagePercentage > 100) {
                $paceStatus = 'over_budget';
            } elseif ($usagePercentage > $expectedUsagePercentage + 10) {
                $paceStatus = 'above_budget_pace';
            } elseif ($usagePercentage < $expectedUsagePercentage - 10) {
                $paceStatus = 'under_budget_pace';
            } else {
                $paceStatus = 'on_budget_pace';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Month-end projection
        |--------------------------------------------------------------------------
        */

        $projectedMonthEndSpending = 0.0;

        if ($hasExpenses && $daysElapsed > 0) {
            $projectedMonthEndSpending = round(
                $actualDailySpending * $daysInMonth,
                2
            );
        }

        $projectedOverrun = null;
        $projectedRemaining = null;

        if ($hasBudget) {
            $projectedOverrun = round(
                max(
                    0,
                    $projectedMonthEndSpending - $budgetAmount
                ),
                2
            );

            $projectedRemaining = round(
                $budgetAmount - $projectedMonthEndSpending,
                2
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Projection confidence
        |--------------------------------------------------------------------------
        */

        $spendingDays = $expenses
            ->filter(function ($expense) {
                return !empty($expense->expense_date);
            })
            ->groupBy(function ($expense) {
                return Carbon::parse(
                    $expense->expense_date
                )->toDateString();
            })
            ->count();

        $projectionConfidence = 'insufficient_data';

        

        if ($hasExpenses) {
            if ($spendingDays >= 7) {
                $projectionConfidence = 'high';
            } elseif ($spendingDays >= 3) {
                $projectionConfidence = 'medium';
            } else {
                $projectionConfidence = 'low';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Budget pressure
        |--------------------------------------------------------------------------
        */

        $pressureLevel = 'no_data';
        $pressureScore = 0;
        $pressureMessage = '';

        if (!$hasBudget && !$hasExpenses) {

            $pressureLevel = 'no_data';

            $pressureMessage =
                'Create a monthly budget and record expenses to start receiving budget intelligence.';

        } elseif (!$hasBudget) {

            $pressureLevel = 'no_budget';

            $pressureMessage =
                'You are tracking spending without a monthly budget.';

        } elseif (!$hasExpenses) {

            $pressureLevel = 'no_expenses';

            $pressureMessage =
                'No expenses have been recorded this month yet.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Pressure score
            |--------------------------------------------------------------------------
            */

            $pressureScore = 0;

            if ($usagePercentage >= 100) {
                $pressureScore += 60;
            } elseif ($usagePercentage >= 90) {
                $pressureScore += 45;
            } elseif ($usagePercentage >= 80) {
                $pressureScore += 30;
            } elseif ($usagePercentage >= 60) {
                $pressureScore += 15;
            }

            if (
                $projectedMonthEndSpending > $budgetAmount
            ) {
                $pressureScore += 30;
            }

            if (
                $usagePercentage >
                $expectedUsagePercentage + 10
            ) {
                $pressureScore += 10;
            }

            $pressureScore = min(
                100,
                $pressureScore
            );

            /*
            |--------------------------------------------------------------------------
            | Pressure label
            |--------------------------------------------------------------------------
            */

            if ($pressureScore >= 80) {

                $pressureLevel = 'critical';

                $pressureMessage =
                    'Your current spending pace indicates a significant risk of exceeding the budget.';

            } elseif ($pressureScore >= 60) {

                $pressureLevel = 'high';

                $pressureMessage =
                    'Your spending pace is putting substantial pressure on the remaining budget.';

            } elseif ($pressureScore >= 30) {

                $pressureLevel = 'moderate';

                $pressureMessage =
                    'Your budget is under moderate pressure. Monitor spending during the remaining days.';

            } else {

                $pressureLevel = 'low';

                $pressureMessage =
                    'Your current spending pace is within a relatively comfortable range.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Category analysis
        |--------------------------------------------------------------------------
        */

        $categoryTotals = [];

        foreach ($expenses as $expense) {

            $category = strtolower(
                trim($expense->category ?? 'other')
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

            $percentage = $spent > 0
                ? round(($amount / $spent) * 100, 1)
                : 0.0;

            $categoryBreakdown[] = [
                'category' => ucfirst($category),
                'amount' => round($amount, 2),
                'percentage' => $percentage,
            ];
        }

        $topCategory = !empty($categoryBreakdown)
            ? $categoryBreakdown[0]['category']
            : null;

        /*
        |--------------------------------------------------------------------------
        | Recommendations
        |--------------------------------------------------------------------------
        */

        $recommendations = [];

        if (!$hasBudget) {

            $recommendations[] = [
                'type' => 'budget',
                'priority' => 'high',
                'title' => 'Set a monthly budget',
                'message' =>
                    'Create a budget so PesaPulse can measure your spending pace and project your month-end position.',
            ];
        }

        if ($hasBudget && $hasExpenses) {

            if ($projectedMonthEndSpending > $budgetAmount) {

                $dailyReduction = $daysRemaining > 0
                    ? round(
                        max(
                            0,
                            $actualDailySpending
                            - (
                                max(0, $remaining)
                                / $daysRemaining
                            )
                        ),
                        2
                    )
                    : 0;

                $recommendations[] = [
                    'type' => 'pace',
                    'priority' => 'high',
                    'title' => 'Reduce daily spending',
                    'message' => $dailyReduction > 0
                        ? "Reduce average daily spending by KES {$dailyReduction} to improve your chances of staying within budget."
                        : 'Your current spending projection indicates that the budget may be exceeded.',
                ];
            }

            if ($topCategory !== null) {

                $recommendations[] = [
                    'type' => 'category',
                    'priority' => 'medium',
                    'title' => 'Review your top spending category',
                    'message' =>
                        "{$topCategory} is currently your largest spending category this month. Review whether non-essential spending can be reduced.",
                ];
            }
        }

        if (
            $hasBudget &&
            $hasExpenses &&
            $pressureScore < 30 &&
            $projectedMonthEndSpending <= $budgetAmount
        ) {

            $recommendations[] = [
                'type' => 'positive',
                'priority' => 'low',
                'title' => 'Your budget is comfortably paced',
                'message' =>
                    'Your spending is currently below the expected budget pace. Continue tracking your expenses to keep the projection reliable.',
            ];
        }

        if (
            $allowedDailySpending !== null &&
         (
            $paceStatus === 'above_budget_pace' ||
            $projectedMonthEndSpending > $budgetAmount
         )
        ) {
           $recommendations[] = [
            'type' => 'daily_limit',
            'priority' => 'high',
            'title' => 'Use a daily spending limit',
            'message' =>
            "With KES "
            . number_format(max(0, $remaining), 2)
            . " remaining, your budget allows about KES "
            . number_format($allowedDailySpending, 2)
            . " per remaining day.",
        ];
        }

        /*
        |--------------------------------------------------------------------------
        | Data quality
        |--------------------------------------------------------------------------
        */

        $canCalculatePace =
            $hasBudget &&
            $hasExpenses &&
            $daysElapsed > 0;

        $canCalculateProjection =
            $hasExpenses &&
            $daysElapsed > 0;

        $dataQuality = [
            'has_budget' => $hasBudget,
            'has_expenses' => $hasExpenses,
            'spending_days' => $spendingDays,
            'can_calculate_pace' => $canCalculatePace,
            'can_calculate_projection' => $canCalculateProjection,
            'projection_confidence' => $projectionConfidence,
        ];

        /*
        |--------------------------------------------------------------------------
        | Final response
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

            'budget' => [
                'amount' => round($budgetAmount, 2),
                'spent' => round($spent, 2),
                'remaining' => round($remaining, 2),
                'usage_percentage' => $usagePercentage,
            ],

            'pace' => [
                'expected_usage_percentage' =>
                    $expectedUsagePercentage,

                'actual_daily_spending' =>
                    $actualDailySpending,

                'allowed_daily_spending' =>
                    $allowedDailySpending,

                'pace_difference_percentage' =>
                    $paceDifference,

                'status' => $paceStatus,
            ],

            'projection' => [
                'projected_month_end_spending' =>
                    $projectedMonthEndSpending,

                'projected_overrun' =>
                    $projectedOverrun,

                'projected_remaining' =>
                    $projectedRemaining,

                'confidence' =>
                    $projectionConfidence,

                'confidence_message' => match ($projectionConfidence) {
                   'high' =>
                   'The projection is based on at least seven spending days.',
                   'medium' =>
                   'The projection is based on several spending days and should be treated as an estimate.',
                   'low' =>
                    'Only a small amount of spending history is available, so the projection may change significantly.',
                    default =>
                     'There is not enough spending data to produce a reliable projection.',
              },
            ],

            'pressure' => [
                'level' => $pressureLevel,
                'score' => $pressureScore,
                'message' => $pressureMessage,
            ],

            'categories' => [
                'top_category' => $topCategory,
                'breakdown' => $categoryBreakdown,
            ],

            'recommendations' => $recommendations,

            'data_quality' => $dataQuality,
        ];
    }
}