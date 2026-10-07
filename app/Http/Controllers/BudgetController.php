<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BudgetController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Resolve requested budget period
    |--------------------------------------------------------------------------
    |
    | If month/year are not supplied, use the current month.
    |
    */

    private function resolvePeriod(Request $request): array
    {
        $validated = $request->validate([
            'month' => [
                'nullable',
                'integer',
                'between:1,12',
            ],

            'year' => [
                'nullable',
                'integer',
                'min:2000',
                'max:2100',
            ],
        ]);

        return [
            'month' => $validated['month'] ?? now()->month,
            'year' => $validated['year'] ?? now()->year,
        ];
    }

    private function assertValidBudgetPeriod(
    int $month,
    int $year
): void {
    if ($year > now()->year ||
        ($year === now()->year && $month > now()->month)) {
        abort(
            response()->json([
                'message' => 'Future budget periods are not available.',
            ], 422)
        );
    }
}

private function getBudgetForPeriod($user, int $month, int $year)
{
    return $user->budgets()
        ->where('month', $month)
        ->where('year', $year)
        ->first();
}

private function getExpensesForPeriod($user, int $month, int $year)
{
    return $user->expenses()
        ->whereMonth('expense_date', $month)
        ->whereYear('expense_date', $year);
}


    /*
    |--------------------------------------------------------------------------
    | Store / update budget
    |--------------------------------------------------------------------------
    |
    | By default this stores the current month's budget.
    |
    | When month/year are supplied, it stores the budget
    | for that specific period.
    |
    */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => [
                'nullable',
                'string',
                'max:100',
            ],

            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999999.99',
            ],

            'month' => [
                'nullable',
                'integer',
                'between:1,12',
            ],

            'year' => [
                'nullable',
                'integer',
                'min:2000',
                'max:2100',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | SECURITY
        |--------------------------------------------------------------------------
        */

        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Budget period
        |--------------------------------------------------------------------------
        */

        $month = (int) $validated['month'] ?? now()->month;
        $year = (int) $validated['year'] ?? now()->year;

        $this->assertValidBudgetPeriod($month, $year);

        /*
        |--------------------------------------------------------------------------
        | Find budget for selected period
        |--------------------------------------------------------------------------
        */

        $budget = Budget::where('user_id', $user->id)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Update existing budget
        |--------------------------------------------------------------------------
        */

        if ($budget) {
            $budget->amount = $validated['amount'];

            /*
            | Preserve the existing client ID.
            */

            if (
                empty($budget->client_id) &&
                !empty($validated['client_id'])
            ) {
                $budget->client_id = $validated['client_id'];
            }

            $budget->save();
        }

        /*
        |--------------------------------------------------------------------------
        | Create budget for selected period
        |--------------------------------------------------------------------------
        */

        else {
            $budget = Budget::create([
                'user_id' => $user->id,
                'client_id' => $validated['client_id'] ?? null,
                'amount' => $validated['amount'],
                'month' => $month,
                'year' => $year,
            ]);
        }

        return response()->json([
            'message' => 'Budget saved successfully.',

            'id' => $budget->id,

            'client_id' => $budget->client_id,

            'budget' => (float) $budget->amount,

            'budget_count' => 1,

            'month' => $budget->month,

            'year' => $budget->year,
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Budget summary
    |--------------------------------------------------------------------------
    */

   public function summary(Request $request)
{
    $user = $request->user();
    $period = $this->resolvePeriod($request);

    $month = $period['month'];
    $year = $period['year'];

    $budget = $this->getBudgetForPeriod($user, $month, $year);
    $budgetCount = $user->budgets()
        ->where('month', $month)
        ->where('year', $year)
        ->count();

    $spent = $this->getExpensesForPeriod($user, $month, $year)->sum('amount');
    $budgetAmount = $budget?->amount ?? 0;

    return response()->json([
        'budget' => (float) $budgetAmount,
        'budget_count' => $budgetCount,
        'spent' => (float) $spent,
        'remaining' => (float) $budgetAmount - (float) $spent,
        'month' => $month,
        'year' => $year,
    ]);
}


    /*
    |--------------------------------------------------------------------------
    | Delete budget
    |--------------------------------------------------------------------------
    */

    public function destroy(Request $request)
    {
        $period = $this->resolvePeriod($request);

        // Prevent deleting future periods
        $this->assertValidBudgetPeriod($period['month'], $period['year']);

        Budget::where(
            'user_id',
            $request->user()->id
        )
            ->where('month', $period['month'])
            ->where('year', $period['year'])
            ->delete();

        return response()->json([
            'message' => 'Budget deleted successfully',

            'month' => $period['month'],

            'year' => $period['year'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Financial insights
    |--------------------------------------------------------------------------
    */

   public function financialInsights(Request $request)
{
    $user = $request->user();
    $period = $this->resolvePeriod($request);

    $month = $period['month'];
    $year = $period['year'];

    // ✅ Centralized helpers
    $budget = $this->getBudgetForPeriod($user, $month, $year);
    $expenses = $this->getExpensesForPeriod($user, $month, $year)
        ->get(['category','amount','expense_date']);

    $hasBudget = $budget !== null;
    $hasExpenses = $expenses->isNotEmpty();

    $budgetAmount = $hasBudget ? (float) $budget->amount : 0;
    $spent = (float) $expenses->sum('amount');
    $remaining = $budgetAmount - $spent;
    $percentage = $budgetAmount > 0 ? round(($spent / $budgetAmount) * 100, 1) : 0;

    /*
    |----------------------------------------------------------------------
    | Budget status & recommendation
    |----------------------------------------------------------------------
    */
    $status = 'no_data';
    $recommendation = '';

    if (!$hasBudget && !$hasExpenses) {
        $status = 'no_data';
    } elseif (!$hasBudget) {
        $status = 'no_budget';
        $recommendation = 'Create a monthly budget to compare your spending against a planned limit.';
    } elseif (!$hasExpenses) {
        $status = 'no_expenses';
        $recommendation = 'Record your expenses to start receiving personalized financial insights.';
    } else {
        if ($percentage >= 200) {
            $status = 'critical';
            $recommendation = 'Your spending is more than double your budget. Immediate review is recommended.';
        } elseif ($percentage >= 100) {
            $status = 'overspent';
            $recommendation = 'You have exceeded your budget. Review non-essential expenses.';
        } elseif ($percentage >= 80) {
            $status = 'warning';
            $recommendation = 'You have used more than 80% of your budget. Spend carefully.';
        } else {
            $status = 'healthy';
            $recommendation = 'You have used less than 80% of your budget. Your spending is under control.';
        }
    }

    /*
    |----------------------------------------------------------------------
    | Category analysis
    |----------------------------------------------------------------------
    */
    $categoryTotals = [];
    foreach ($expenses as $expense) {
        $category = strtolower(trim($expense->category ?? 'other'));
        $categoryTotals[$category] = ($categoryTotals[$category] ?? 0) + (float) $expense->amount;
    }

    $topCategory = null;
    $topAmount = 0;
    foreach ($categoryTotals as $category => $amount) {
        if ($amount > $topAmount) {
            $topAmount = $amount;
            $topCategory = $category;
        }
    }

    $categoryAdvice = '';
    if ($topCategory) {
        switch ($topCategory) {
            case 'food': $categoryAdvice = 'Food spending is your highest expense. Consider meal planning and reducing takeout.'; break;
            case 'transport': $categoryAdvice = 'Transport costs are high. Consider public transport or carpooling.'; break;
            case 'shopping': $categoryAdvice = 'Shopping expenses are leading your spending. Focus on essential purchases.'; break;
            case 'entertainment': $categoryAdvice = 'Entertainment spending is high this month. Review subscriptions and leisure costs.'; break;
            case 'bills': $categoryAdvice = 'Bills are your highest expense. Consider reviewing subscriptions, utilities, and renegotiating plans where possible.'; break;
            case 'health': $categoryAdvice = 'Health expenses are significant. Ensure they are necessary and check for possible insurance or cost-saving options.'; break;
            case 'education': $categoryAdvice = 'Education spending is an investment. Track it carefully and ensure it aligns with your goals.'; break;
            case 'other': $categoryAdvice = 'Uncategorized expenses are high. Try to categorize your spending for better financial tracking.'; break;
            default: $categoryAdvice = "Your highest spending category is {$topCategory}. Consider reviewing those expenses."; break;
        }
    }

    $categoryBreakdown = $expenses
        ->groupBy(fn($expense) => strtolower(trim($expense->category ?? 'other')))
        ->map(fn($items, $category) => [
            'category' => ucfirst($category),
            'total' => round((float) $items->sum('amount'), 2),
        ])
        ->values();

    /*
    |----------------------------------------------------------------------
    | Daily spending trend
    |----------------------------------------------------------------------
    */
    $dailySpending = ['Mon'=>0,'Tue'=>0,'Wed'=>0,'Thu'=>0,'Fri'=>0,'Sat'=>0,'Sun'=>0];
    foreach ($expenses as $expense) {
        if ($expense->expense_date) {
            $day = \Carbon\Carbon::parse($expense->expense_date)->format('D');
            if (isset($dailySpending[$day])) {
                $dailySpending[$day] += (float) $expense->amount;
            }
        }
    }
    foreach ($dailySpending as $day => $amount) {
        $dailySpending[$day] = round($amount, 2);
    }

    $highestDay = null;
    $highestDayAmount = 0;
    foreach ($dailySpending as $day => $amount) {
        if ($amount > $highestDayAmount) {
            $highestDayAmount = $amount;
            $highestDay = $day;
        }
    }

    $daysWithExpenses = $expenses
        ->filter(fn($expense) => !empty($expense->expense_date))
        ->groupBy(fn($expense) => \Carbon\Carbon::parse($expense->expense_date)->toDateString())
        ->count();

    $averageDaily = $daysWithExpenses > 0 ? round($spent / $daysWithExpenses, 2) : 0;

    /*
    |----------------------------------------------------------------------
    | Estimated month-end spending
    |----------------------------------------------------------------------
    */
    $daysInMonth = \Carbon\Carbon::create($year, $month, 1)->daysInMonth;
    $isCurrentPeriod = $month === now()->month && $year === now()->year;

    if (!$hasExpenses) {
        $estimatedMonthEnd = 0;
    } elseif (!$isCurrentPeriod) {
        $estimatedMonthEnd = round($spent, 2);
    } else {
        $today = now()->day;
        $estimatedMonthEnd = $today > 0 ? round(($spent / $today) * $daysInMonth, 2) : 0;
    }

    /*
    |----------------------------------------------------------------------
    | Financial health score
    |----------------------------------------------------------------------
    */
    $score = 0;
    $healthLabel = 'No Data';

    if ($hasBudget && $hasExpenses) {
        $score = 100;
        if ($percentage >= 100) $score -= 45;
        elseif ($percentage >= 80) $score -= 20;
        if ($remaining <= 0) $score -= 20;
        if ($estimatedMonthEnd > $budgetAmount) $score -= 15;

        $score = max(0, min(100, $score));

        if ($score >= 90) $healthLabel = 'Excellent';
        elseif ($score >= 70) $healthLabel = 'Good';
        elseif ($score >= 50) $healthLabel = 'Fair';
        elseif ($score >= 30) $healthLabel = 'Poor';
        else $healthLabel = 'Critical';
    }

    /*
    |----------------------------------------------------------------------
    | Final response
    |----------------------------------------------------------------------
    */
    return response()->json([
        'period' => ['month' => $month, 'year' => $year],
        'budget' => round($budgetAmount, 2),
        'spent' => round($spent, 2),
        'remaining' => round($remaining, 2),
        'usage_percentage' => $percentage,
        'status' => $status,
        'budget_status' => $status,
        'has_budget' => $hasBudget,
        'has_expenses' => $hasExpenses,
        'has_enough_data_for_health' => $hasBudget && $hasExpenses,
        'recommendation' => $recommendation,
        'top_category' => $topCategory,
        'category_advice' => $categoryAdvice,
        'category_breakdown' => $categoryBreakdown,
        'daily_spending' => $dailySpending,
        'highest_spending_day' => ['day' => $highestDay, 'amount' => round($highestDayAmount, 2)],
        'average_daily_spending' => $averageDaily,
        'estimated_month_end_spending' => $estimatedMonthEnd,
        'financial_health_score' => $score,
        'financial_health_label' => $healthLabel,
    ]);
}

}