<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    /**
     * Display authenticated user's expenses.
     */
    public function index(Request $request)
    {
        return $request->user()
            ->expenses()
            ->latest()
            ->paginate(5);
    }

    /**
     * Search authenticated user's expenses.
     */
    public function search(Request $request)
    {
        $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        $query = $request->user()->expenses();

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
            $query->latest()->paginate(5)
        );
    }

    /**
     * Create a new expense.
     */
    public function store(Request $request)
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

        $expense = $request->user()->expenses()->create([
            'title' => trim($validated['title']),
            'amount' => $validated['amount'],
            'category' => trim($validated['category']),
            'expense_date' => $validated['expense_date'],
            'description' => isset($validated['description'])
                ? trim($validated['description'])
                : null,
        ]);

        return response()->json($expense, 201);
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
        $expenses = $request->user()->expenses()->get();

        $total = $expenses->sum('amount');

        $categories = $expenses
            ->groupBy('category')
            ->map(function ($items) {
                return $items->sum('amount');
            });

        return response()->json([
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

        $recentExpenses = $user->expenses()
            ->latest('expense_date')
            ->latest('id')
            ->take(3)
            ->get();

        return response()->json([
            'summary' => [
                'total_expenses' => $user->expenses()->sum('amount'),

                'total_count' => $user->expenses()->count(),

                'categories' => $user->expenses()
                    ->distinct('category')
                    ->count('category'),
            ],

            'recent_expenses' => $recentExpenses,
        ]);
    }
}

