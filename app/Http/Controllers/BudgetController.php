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
