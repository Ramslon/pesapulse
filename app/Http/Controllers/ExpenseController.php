<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class ExpenseController extends Controller
{
    /**
     * Display authenticated user's expenses.
     */
    public function index(Request $request)
    {
    $validated = $request->validate([
        'month' => ['nullable', 'integer', 'between:1,12'],
        'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
    ]);

    $month = $validated['month'] ?? now()->month;
    $year = $validated['year'] ?? now()->year;

    return $request->user()
        ->expenses()
        ->whereMonth('expense_date', $month)
        ->whereYear('expense_date', $year)
        ->latest('expense_date')
        ->latest('id')
        ->paginate(5);
    }
    /**
     * Search authenticated user's expenses.
     */
    public function search(Request $request)
    {
    $validated = $request->validate([
        'title' => ['nullable', 'string', 'max:255'],
        'category' => ['nullable', 'string', 'max:100'],
        'month' => ['nullable', 'integer', 'between:1,12'],
        'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
    ]);

    $month = $validated['month'] ?? now()->month;
    $year = $validated['year'] ?? now()->year;

    $query = $request->user()
        ->expenses()
        ->whereMonth('expense_date', $month)
        ->whereYear('expense_date', $year);

    if ($request->filled('title')) {
        $query->where(
            'title',
            'LIKE',
            '%' . trim($request->title) . '%'
        );
    }

    if ($request->filled('category')) {
        $query->where(
            'category',
            'LIKE',
            '%' . trim($request->category) . '%'
        );
    }

    return response()->json(
        $query
            ->latest('expense_date')
            ->latest('id')
            ->paginate(5)
    );
    }

    /**
    * Create a new expense.
   */
   public function store(Request $request)
   {
    $validated = $request->validate([
        'client_id' => [
            'nullable',
            'string',
            'max:100',
        ],

        'title' => [
            'required',
            'string',
            'max:255',
        ],

        'amount' => [
            'required',
            'numeric',
            'min:0.01',
            'max:999999999.99',
        ],

        'category' => [
            'required',
            'string',
            'max:100',
        ],

        'expense_date' => [
            'required',
            'date',
        ],

        'description' => [
            'nullable',
            'string',
            'max:1000',
        ],
    ]);

    $user = $request->user();

    $clientId = isset($validated['client_id'])
        ? trim($validated['client_id'])
        : null;

    /*
     * If the same client_id already exists for this user,
     * return the original expense instead of creating another one.
     */
    if ($clientId !== null && $clientId !== '') {
        $existingExpense = $user->expenses()
            ->where('client_id', $clientId)
            ->first();

        if ($existingExpense) {
            return response()->json([
                ...$existingExpense->toArray(),
                'deduplicated' => true,
            ], 200);
        }
    }

    try {
        $expense = $user->expenses()->create([
            'client_id' => $clientId,
            'title' => trim($validated['title']),
            'amount' => $validated['amount'],
            'category' => trim($validated['category']),
            'expense_date' => $validated['expense_date'],
            'description' => isset($validated['description'])
                ? trim($validated['description'])
                : null,
        ]);

        return response()->json([
            ...$expense->toArray(),
            'deduplicated' => false,
        ], 201);

    } catch (\Illuminate\Database\QueryException $e) {

        /*
         * A simultaneous identical request may have inserted
         * the same client_id before this request completed.
         *
         * MySQL duplicate-key error = 1062.
         */
        if (
            $clientId !== null &&
            $clientId !== '' &&
            isset($e->errorInfo[1]) &&
            (int) $e->errorInfo[1] === 1062 &&
            str_contains(
                strtolower($e->getMessage()),
                'expenses_user_client_unique'
            )
        ) {
            $existingExpense = $user->expenses()
                ->where('client_id', $clientId)
                ->first();

            if ($existingExpense) {
                return response()->json([
                    ...$existingExpense->toArray(),
                    'deduplicated' => true,
                ], 200);
            }
        }

        throw $e;
     }
    }

    /**
     * Display one expense belonging to authenticated user.
     */
    public function show(Request $request, $id)
    {
        $expense = $request->user()
            ->expenses()
            ->findOrFail($id);

        return response()->json($expense);
    }

    /**
     * Update an expense belonging to authenticated user.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999999.99',
            ],

            'category' => [
                'required',
                'string',
                'max:100',
            ],

            'expense_date' => [
                'required',
                'date',
            ],

            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $expense = $request->user()
            ->expenses()
            ->findOrFail($id);

        $expense->update([
            'title' => trim($validated['title']),
            'amount' => $validated['amount'],
            'category' => trim($validated['category']),
            'expense_date' => $validated['expense_date'],
            'description' => isset($validated['description'])
                ? trim($validated['description'])
                : null,
        ]);

        return response()->json([
            'message' => 'Expense updated successfully',
            'expense' => $expense->fresh(),
        ]);
    }

    /**
     * Delete an expense belonging to authenticated user.
     */
    public function destroy(Request $request, $id)
    {
        $expense = $request->user()
            ->expenses()
            ->findOrFail($id);

        $expense->delete();

        return response()->json([
            'message' => 'Expense deleted successfully',
        ]);
    }

    /**
     * Expense analytics for authenticated user.
     */
    public function analytics(Request $request)
    {
    $validated = $request->validate([
        'month' => ['nullable', 'integer', 'between:1,12'],
        'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
    ]);

    $month = $validated['month'] ?? now()->month;
    $year = $validated['year'] ?? now()->year;

    $expenses = $request->user()
        ->expenses()
        ->whereMonth('expense_date', $month)
        ->whereYear('expense_date', $year)
        ->get();

    $total = $expenses->sum('amount');

    $categories = $expenses
        ->groupBy('category')
        ->map(function ($items) {
            return $items->sum('amount');
        });

    return response()->json([
        'period' => [
            'month' => $month,
            'year' => $year,
        ],

        'total_spending' => $total,

        'categories' => $categories,
    ]);
    }

    /**
     * Dashboard for authenticated user.
     */
    public function dashboard(Request $request)
    {
    $user = $request->user();

    $month = now()->month;
    $year = now()->year;

    $currentExpenses = $user->expenses()
        ->whereMonth('expense_date', $month)
        ->whereYear('expense_date', $year);

    $recentExpenses = (clone $currentExpenses)
        ->latest('expense_date')
        ->latest('id')
        ->take(3)
        ->get();

    return response()->json([
        'summary' => [
            'total_expenses' => (clone $currentExpenses)->sum('amount'),

            'total_count' => (clone $currentExpenses)->count(),

            'categories' => (clone $currentExpenses)
                ->distinct('category')
                ->count('category'),
        ],

        'recent_expenses' => $recentExpenses,
    ]);
   }
}