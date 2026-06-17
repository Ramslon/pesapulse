<?php

namespace App\Http\Controllers;

use App\Models\Budget;

use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function store(Request $request)
{
    $budget = Budget::updateOrCreate(
        [
            'user_id' => $request->user()->id,
            'month' => now()->month,
            'year' => now()->year,
        ],
        [
            'amount' => $request->amount,
        ]
    );

    return response()->json($budget);
}

public function summary(Request $request)
{
    $user = $request->user();

    $budget = $user->budgets()
        ->where('month', now()->month)
        ->where('year', now()->year)
        ->first();

    $spent = $user->expenses()
        ->whereMonth('created_at', now()->month)
        ->sum('amount');

    return response()->json([
        'budget' => $budget?->amount ?? 0,
        'spent' => $spent,
        'remaining' => ($budget?->amount ?? 0) - $spent,
    ]);
}
    
   public function financialInsights(Request $request)
{
    $user = $request->user();

    $budget = $user->budgets()->latest()->first();

    if (!$budget) {
        return response()->json([
            'message' => 'No budget found'
        ]);
    }

    $spent = $user->expenses()->sum('amount');

    $budgetAmount = $budget->amount;

    $remaining = $budgetAmount - $spent;

    $percentage =
        $budgetAmount > 0
        ? round(($spent / $budgetAmount) * 100, 1)
        : 0;

    $status = 'healthy';

    $recommendation =
        'Your spending is under control.';

    if ($percentage >= 100) {
        $status = 'overspent';

        $recommendation =
            'You have exceeded your budget. Review non-essential expenses.';
        
    }
    elseif ($percentage >= 80) {
        $status = 'warning';

        $recommendation =
            'You have used more than 80% of your budget. Spend carefully.';
    }

    $expenses = $user->expenses;

    $categoryTotals = [];

     foreach ($expenses as $expense) {
    $category = $expense->category;

    if (!isset($categoryTotals[$category])) {
        $categoryTotals[$category] = 0;
    }

    $categoryTotals[$category] += $expense->amount;
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

        default:
            $categoryAdvice =
                "Your highest spending category is $topCategory. Consider reviewing those expenses.";
    }
}

    return response()->json([
        'budget' => $budgetAmount,
        'spent' => $spent,
        'remaining' => $remaining,
        'usage_percentage' => $percentage,
        'status' => $status,
        'recommendation' => $recommendation,
    ]);
}
}
