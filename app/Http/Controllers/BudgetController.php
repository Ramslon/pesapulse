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
            'required',
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
            'required',
            'integer',
            'between:1,12',
        ],

        'year' => [
            'required',
            'integer',
            'between:2020,2100',
        ],
    ]);

    /*
    |--------------------------------------------------------------------------
    | SECURITY
    |--------------------------------------------------------------------------
    | Never accept user_id from the client.
    | The authenticated Sanctum user determines ownership.
    */

    $user = $request->user();

    /*
    |--------------------------------------------------------------------------
    | Find existing budget
    |--------------------------------------------------------------------------
    */

    $budget = Budget::where('user_id', $user->id)
        ->where('client_id', $validated['client_id'])
        ->first();

    /*
    |--------------------------------------------------------------------------
    | Update existing budget
    |--------------------------------------------------------------------------
    */

    if ($budget) {

        $budget->amount = $validated['amount'];
        $budget->month = $validated['month'];
        $budget->year = $validated['year'];

        $budget->save();

    } else {

        /*
        |--------------------------------------------------------------------------
        | Create new budget
        |--------------------------------------------------------------------------
        |
        | user_id is assigned explicitly by the server.
        | It is NOT taken from the request.
        |
        */

        $budget = new Budget();

        $budget->user_id = $user->id;
        $budget->client_id = $validated['client_id'];
        $budget->amount = $validated['amount'];
        $budget->month = $validated['month'];
        $budget->year = $validated['year'];

        $budget->save();
    }

    return response()->json($budget, 200);
}


public function summary(Request $request)
{
    $user = $request->user();

    $budget = $user->budgets()
        ->where('month', now()->month)
        ->where('year', now()->year)
        ->first();

    $budgetCount = $user->budgets()
        ->where('month', now()->month)
        ->where('year', now()->year)
        ->count();

    $spent = $user->expenses()
        ->whereMonth('expense_date', now()->month)
        ->whereYear('expense_date', now()->year)
        ->sum('amount');

    $budgetAmount = $budget?->amount ?? 0;

    return response()->json([
        'budget' => $budgetAmount,
        'budget_count' => $budgetCount,
        'spent' => $spent,
        'remaining' => $budgetAmount - $spent,
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

    // Get latest budget
    $budget = $user->budgets()->latest()->first();

    if (!$budget) {
    return response()->json([
        'message' => 'No budget found'
    ], 404);
}

    // Efficient expense query (only needed columns)
    $expenses = $user->expenses()->get(['category', 'amount']);

    // Calculate total spent
    $spent = $expenses->sum('amount');

    $budgetAmount = $budget->amount;
    $remaining = $budgetAmount - $spent;

    $percentage = $budgetAmount > 0
        ? round(($spent / $budgetAmount) * 100, 1)
        : 0;

    // Budget status logic
    $status = 'healthy';
    

    $recommendation = 'Your spending is under control.';

    if ($percentage >= 200) {
        $status = 'critical';
        $recommendation =
        'Your spending is more than double your budget. Immediate review is recommended.';
    }
    elseif ($percentage >= 100) {
        $status = 'overspent';
        $recommendation = 'You have exceeded your budget. Review non-essential expenses.';
    } elseif ($percentage >= 80) {
        $status = 'warning';
        $recommendation = 'You have used more than 80% of your budget. Spend carefully.';
    }
    elseif ($percentage < 80) {
        $status = 'healthy';
        $recommendation = 'You have used less than 80% of your budget.Your spending is under control.';
    }

    // CATEGORY ANALYSIS
    $categoryTotals = [];

    foreach ($expenses as $expense) {
        $category = strtolower(trim($expense->category ?? 'Other'));

        if (!isset($categoryTotals[$category])) {
            $categoryTotals[$category] = 0;
        }

        $categoryTotals[$category] += $expense->amount;
    }

    // Find top spending category
    $topCategory = null;
    $topAmount = 0;

    foreach ($categoryTotals as $category => $amount) {
        if ($amount > $topAmount) {
            $topAmount = $amount;
            $topCategory = $category;
        }
    }

    // SMART CATEGORY ADVICE
    $categoryAdvice = '';

    if ($topCategory) {
        switch (strtolower($topCategory)) {

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
            "Your highest spending category is $topCategory. Consider reviewing those expenses.";
        }
    }

    $categoryBreakdown = $expenses
    ->groupBy('category')
    ->map(function ($items, $category) {
        return [
            'category' => ucfirst($category),
            'total' => round($items->sum('amount'), 2),
        ];
    })
    ->values();

    // DAILY SPENDING TREND
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

    $day = \Carbon\Carbon::parse($expense->expense_date)->format('D');

    if (isset($dailySpending[$day])) {
        $dailySpending[$day] += $expense->amount;
    }
    }

    // Highest spending day
    $highestDay = null;
    $highestDayAmount = 0;

    foreach ($dailySpending as $day => $amount) {

    if ($amount > $highestDayAmount) {

        $highestDayAmount = $amount;

        $highestDay = $day;
    }
    }

    $daysWithExpenses = $expenses
    ->groupBy(function ($expense) {
        return \Carbon\Carbon::parse($expense->expense_date)->toDateString();
    })
    ->count();

    $averageDaily = $daysWithExpenses > 0
    ? round($spent / $daysWithExpenses, 2)
    : 0;

    $today = now()->day;

    $daysInMonth = now()->daysInMonth;

    $estimatedMonthEnd = $today > 0
    ? round(($spent / $today) * $daysInMonth, 2)
    : 0;


    // Financial Health Score (0-100)

   $score = 100;

// Budget usage impact
   if ($percentage >= 100) {

    $score -= 45;

   }
   elseif ($percentage >= 80) {

    $score -= 20;

   }

// Remaining budget impact
   if ($remaining <= 0) {

    $score -= 20;

    }

// Large estimated overspending
    if ($estimatedMonthEnd > $budgetAmount) {

    $score -= 15;

    }

    $score = max(0, min(100, $score));

    $healthLabel = 'Excellent';

if ($score < 90) {
    $healthLabel = 'Good';
}

if ($score < 70) {
    $healthLabel = 'Fair';
}

if ($score < 50) {
    $healthLabel = 'Poor';
}

if ($score < 30) {
    $healthLabel = 'Critical';
}
   

    // FINAL RESPONSE
    return response()->json([
        'budget' => $budgetAmount,
        'spent' => $spent,
        'remaining' => $remaining,
        'usage_percentage' => $percentage,
        'status' => $status,
        'budget_status' => $status,
        'recommendation' => $recommendation,
        'top_category' => $topCategory,
        'category_advice' => $categoryAdvice,
        'category_breakdown' => $categoryBreakdown,
        'daily_spending' => $dailySpending,
        'highest_spending_day' => [
        'day' => $highestDay,
        'amount' => $highestDayAmount,
           ],

        'average_daily_spending' => $averageDaily,

        'estimated_month_end_spending' => $estimatedMonthEnd,

        'financial_health_score' => $score,

        'financial_health_label' => $healthLabel,
       ]);
        


      }
    }
