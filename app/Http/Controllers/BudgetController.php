<?php

namespace App\Http\Controllers;

use App\Models\Budget;

use Illuminate\Http\Request;

class BudgetController extends Controller
{
    
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
    ]);

    /*
    |--------------------------------------------------------------------------
    | SECURITY
    |--------------------------------------------------------------------------
    | The authenticated Sanctum user determines ownership.
    | Never accept user_id from the client.
    */

    $user = $request->user();

    /*
    |--------------------------------------------------------------------------
    | Current budget period
    |--------------------------------------------------------------------------
    | The server determines the month and year.
    | The client cannot choose or manipulate the budget period.
    */

    $month = now()->month;
    $year = now()->year;

    /*
    |--------------------------------------------------------------------------
    | Find the authenticated user's current-month budget
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
        | Keep the existing client_id.
        |
        | This is important because editing a budget must not create
        | a new client identity.
        |
        | If an old budget has no client_id, use the client_id
        | supplied by the client.
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
    | Create new budget
    |--------------------------------------------------------------------------
    */

    else {
        $budget = new Budget();

        $budget->user_id = $user->id;
        $budget->client_id = $validated['client_id'] ?? null;
        $budget->amount = $validated['amount'];
        $budget->month = $month;
        $budget->year = $year;

        $budget->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Return saved budget
    |--------------------------------------------------------------------------
    */

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

public function summary(Request $request)
{
    $user = $request->user();

    $month = now()->month;
    $year = now()->year;

    $budget = $user->budgets()
        ->where('month', $month)
        ->where('year', $year)
        ->latest()
        ->first();

    $budgetCount = $user->budgets()
        ->where('month', $month)
        ->where('year', $year)
        ->count();

    $spent = $user->expenses()
        ->whereMonth('expense_date', $month)
        ->whereYear('expense_date', $year)
        ->sum('amount');

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


public function destroy(Request $request)
{
    Budget::where('user_id', $request->user()->id)
        ->where('month', now()->month)
        ->where('year', now()->year)
        ->delete();

    return response()->json([
        'message' => 'Budget deleted successfully'
    ]);
}
    
  public function financialInsights(Request $request)
{
    $user = $request->user();

    /*
    |--------------------------------------------------------------------------
    | Determine the period being analyzed
    |--------------------------------------------------------------------------
    */

    $currentMonth = now()->month;
    $currentYear = now()->year;

    /*
    |--------------------------------------------------------------------------
    | Get the budget for the current month
    |--------------------------------------------------------------------------
    */

    $budget = $user->budgets()
        ->where('month', $currentMonth)
        ->where('year', $currentYear)
        ->latest()
        ->first();

    /*
    |--------------------------------------------------------------------------
    | Get only expenses from the current month
    |--------------------------------------------------------------------------
    */

    $expenses = $user->expenses()
        ->whereMonth('expense_date', $currentMonth)
        ->whereYear('expense_date', $currentYear)
        ->get([
            'category',
            'amount',
            'expense_date',
        ]);

    $hasBudget = $budget !== null;
    $hasExpenses = $expenses->isNotEmpty();

    /*
    |--------------------------------------------------------------------------
    | Basic financial values
    |--------------------------------------------------------------------------
    */

    $budgetAmount = $hasBudget
        ? (float) $budget->amount
        : 0;

    $spent = (float) $expenses->sum('amount');

    $remaining = $budgetAmount - $spent;

    $percentage = $budgetAmount > 0
        ? round(($spent / $budgetAmount) * 100, 1)
        : 0;

    /*
    |--------------------------------------------------------------------------
    | Budget status
    |--------------------------------------------------------------------------
    */

    $status = 'no_data';
    $recommendation = '';

    if (!$hasBudget && !$hasExpenses) {

        $status = 'no_data';

        $recommendation = '';

    } elseif (!$hasBudget) {

        $status = 'no_budget';

        $recommendation =
            'Create a monthly budget to compare your spending against a planned limit.';

    } elseif (!$hasExpenses) {

        $status = 'no_expenses';

        $recommendation =
            'Record your expenses to start receiving personalized financial insights.';

    } else {

        if ($percentage >= 200) {

            $status = 'critical';

            $recommendation =
                'Your spending is more than double your budget. Immediate review is recommended.';

        } elseif ($percentage >= 100) {

            $status = 'overspent';

            $recommendation =
                'You have exceeded your budget. Review non-essential expenses.';

        } elseif ($percentage >= 80) {

            $status = 'warning';

            $recommendation =
                'You have used more than 80% of your budget. Spend carefully.';

        } else {

            $status = 'healthy';

            $recommendation =
                'You have used less than 80% of your budget. Your spending is under control.';
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

        if (!isset($categoryTotals[$category])) {
            $categoryTotals[$category] = 0;
        }

        $categoryTotals[$category] += (float) $expense->amount;
    }

    /*
    |--------------------------------------------------------------------------
    | Find top spending category
    |--------------------------------------------------------------------------
    */

    $topCategory = null;
    $topAmount = 0;

    foreach ($categoryTotals as $category => $amount) {

        if ($amount > $topAmount) {

            $topAmount = $amount;
            $topCategory = $category;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Smart category advice
    |--------------------------------------------------------------------------
    */

    $categoryAdvice = '';

    if ($topCategory) {

        switch ($topCategory) {

            case 'food':

                $categoryAdvice =
                    'Food spending is your highest expense. Consider meal planning and reducing takeout.';

                break;

            case 'transport':

                $categoryAdvice =
                    'Transport costs are high. Consider public transport or carpooling.';

                break;

            case 'shopping':

                $categoryAdvice =
                    'Shopping expenses are leading your spending. Focus on essential purchases.';

                break;

            case 'entertainment':

                $categoryAdvice =
                    'Entertainment spending is high this month. Review subscriptions and leisure costs.';

                break;

            case 'bills':

                $categoryAdvice =
                    'Bills are your highest expense. Consider reviewing subscriptions, utilities, and renegotiating plans where possible.';

                break;

            case 'health':

                $categoryAdvice =
                    'Health expenses are significant. Ensure they are necessary and check for possible insurance or cost-saving options.';

                break;

            case 'education':

                $categoryAdvice =
                    'Education spending is an investment. Track it carefully and ensure it aligns with your goals.';

                break;

            case 'other':

                $categoryAdvice =
                    'Uncategorized expenses are high. Try to categorize your spending for better financial tracking.';

                break;

            default:

                $categoryAdvice =
                    "Your highest spending category is {$topCategory}. Consider reviewing those expenses.";

                break;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Category breakdown
    |--------------------------------------------------------------------------
    */

    $categoryBreakdown = $expenses
        ->groupBy(function ($expense) {

            return strtolower(
                trim($expense->category ?? 'other')
            );

        })
        ->map(function ($items, $category) {

            return [
                'category' => ucfirst($category),
                'total' => round(
                    (float) $items->sum('amount'),
                    2
                ),
            ];

        })
        ->values();

    /*
    |--------------------------------------------------------------------------
    | Daily spending trend
    |--------------------------------------------------------------------------
    */

    $dailySpending = [
        'Mon' => 0,
        'Tue' => 0,
        'Wed' => 0,
        'Thu' => 0,
        'Fri' => 0,
        'Sat' => 0,
        'Sun' => 0,
    ];

    foreach ($expenses as $expense) {

        if (!$expense->expense_date) {
            continue;
        }

        $day = \Carbon\Carbon::parse(
            $expense->expense_date
        )->format('D');

        if (isset($dailySpending[$day])) {

            $dailySpending[$day] +=
                (float) $expense->amount;
        }
    }

    foreach ($dailySpending as $day => $amount) {

        $dailySpending[$day] = round(
            $amount,
            2
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Highest spending day
    |--------------------------------------------------------------------------
    */

    $highestDay = null;
    $highestDayAmount = 0;

    foreach ($dailySpending as $day => $amount) {

        if ($amount > $highestDayAmount) {

            $highestDayAmount = $amount;
            $highestDay = $day;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Average daily spending
    |--------------------------------------------------------------------------
    */

    $daysWithExpenses = $expenses
        ->filter(function ($expense) {

            return !empty($expense->expense_date);

        })
        ->groupBy(function ($expense) {

            return \Carbon\Carbon::parse(
                $expense->expense_date
            )->toDateString();

        })
        ->count();

    $averageDaily = $daysWithExpenses > 0
        ? round(
            $spent / $daysWithExpenses,
            2
        )
        : 0;

    /*
    |--------------------------------------------------------------------------
    | Estimated month-end spending
    |--------------------------------------------------------------------------
    */

    $today = now()->day;
    $daysInMonth = now()->daysInMonth;

    $estimatedMonthEnd =
        $hasExpenses && $today > 0
            ? round(
                ($spent / $today) * $daysInMonth,
                2
            )
            : 0;

    /*
    |--------------------------------------------------------------------------
    | Financial health score
    |--------------------------------------------------------------------------
    */

    $score = 0;
    $healthLabel = 'No Data';

    /*
    | A meaningful financial health score requires
    | both a budget and expenses for the same month.
    */

    if ($hasBudget && $hasExpenses) {

        $score = 100;

        /*
        | Budget usage impact
        */

        if ($percentage >= 100) {

            $score -= 45;

        } elseif ($percentage >= 80) {

            $score -= 20;
        }

        /*
        | No remaining budget
        */

        if ($remaining <= 0) {

            $score -= 20;
        }

        /*
        | Estimated month-end overspending
        */

        if ($estimatedMonthEnd > $budgetAmount) {

            $score -= 15;
        }

        $score = max(
            0,
            min(100, $score)
        );

        /*
        | Health label
        */

        if ($score >= 90) {

            $healthLabel = 'Excellent';

        } elseif ($score >= 70) {

            $healthLabel = 'Good';

        } elseif ($score >= 50) {

            $healthLabel = 'Fair';

        } elseif ($score >= 30) {

            $healthLabel = 'Poor';

        } else {

            $healthLabel = 'Critical';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Final response
    |--------------------------------------------------------------------------
    */

    return response()->json([

        'period' => [
            'month' => $currentMonth,
            'year' => $currentYear,
        ],

        'budget' => round(
            $budgetAmount,
            2
        ),

        'spent' => round(
            $spent,
            2
        ),

        'remaining' => round(
            $remaining,
            2
        ),

        'usage_percentage' => $percentage,

        'status' => $status,

        'budget_status' => $status,

        'has_budget' => $hasBudget,

        'has_expenses' => $hasExpenses,

        'has_enough_data_for_health' =>
            $hasBudget && $hasExpenses,

        'recommendation' => $recommendation,

        'top_category' => $topCategory,

        'category_advice' => $categoryAdvice,

        'category_breakdown' => $categoryBreakdown,

        'daily_spending' => $dailySpending,

        'highest_spending_day' => [
            'day' => $highestDay,
            'amount' => round(
                $highestDayAmount,
                2
            ),
        ],

        'average_daily_spending' => $averageDaily,

        'estimated_month_end_spending' =>
            $estimatedMonthEnd,

        'financial_health_score' => $score,

        'financial_health_label' => $healthLabel,
    ]);
}
    }
