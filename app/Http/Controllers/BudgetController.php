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
    
}
