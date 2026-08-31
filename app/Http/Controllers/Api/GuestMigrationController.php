<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuestMigrationController extends Controller
{
    public function migrate(Request $request)
    {
        $validated = $request->validate([

            /*
            |--------------------------------------------------------------------------
            | Expenses
            |--------------------------------------------------------------------------
            */

            'expenses' => [
                'nullable',
                'array',
                'max:500',
            ],

            'expenses.*' => [
                'required',
                'array',
            ],

            'expenses.*.client_id' => [
                'required',
                'string',
                'max:100',
            ],

            'expenses.*.title' => [
                'required',
                'string',
                'max:255',
            ],

            'expenses.*.amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999999.99',
            ],

            'expenses.*.category' => [
                'required',
                'string',
                'max:100',
            ],

            'expenses.*.expense_date' => [
                'required',
                'date',
            ],

            'expenses.*.description' => [
                'nullable',
                'string',
                'max:1000',
            ],

            /*
            |--------------------------------------------------------------------------
            | Goals
            |--------------------------------------------------------------------------
            */

            'goals' => [
                'nullable',
                'array',
                'max:100',
            ],

            'goals.*' => [
                'required',
                'array',
            ],

            'goals.*.client_id' => [
                'required',
                'string',
                'max:100',
            ],

            'goals.*.title' => [
                'required',
                'string',
                'max:255',
            ],

            'goals.*.target_amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999999.99',
            ],

            'goals.*.saved_amount' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999.99',
            ],

            'goals.*.target_date' => [
                'nullable',
                'date',
            ],

            /*
            |--------------------------------------------------------------------------
            | Budgets
            |--------------------------------------------------------------------------
            */

            'budgets' => [
                'nullable',
                'array',
                'max:100',
            ],

            'budgets.*' => [
                'required',
                'array',
            ],

            'budgets.*.client_id' => [
                'required',
                'string',
                'max:100',
            ],

            'budgets.*.amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999999.99',
            ],

            'budgets.*.month' => [
                'required',
                'integer',
                'between:1,12',
            ],

            'budgets.*.year' => [
                'required',
                'integer',
                'between:2020,2100',
            ],

            /*
            |--------------------------------------------------------------------------
            | Settings
            |--------------------------------------------------------------------------
            */

            'settings' => [
                'nullable',
                'array',
                'max:20',
            ],

            'settings.*' => [
                'required',
                'array',
            ],

            'settings.*.key' => [
                'required',
                'string',
                'in:daily_reminder,expense_alerts,weekly_summary,dark_mode,notifications_enabled',
            ],

            'settings.*.value' => [
                'required',
                'boolean',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |--------------------------------------------------------------------------
        | Never trust a user_id or owner_id supplied by the client.
        | Sanctum determines the authenticated user.
        |--------------------------------------------------------------------------
        */

        $user = $request->user();

        DB::transaction(function () use ($validated, $user) {

            /*
            |--------------------------------------------------------------------------
            | Expenses
            |--------------------------------------------------------------------------
            */

            foreach ($validated['expenses'] ?? [] as $expense) {

                DB::table('expenses')->updateOrInsert(
                    [
                        'user_id' => $user->id,
                        'client_id' => $expense['client_id'],
                    ],
                    [
                        'title' => trim($expense['title']),
                        'amount' => $expense['amount'],
                        'category' => trim($expense['category']),
                        'expense_date' => $expense['expense_date'],
                        'description' => isset($expense['description'])
                            ? trim($expense['description'])
                            : null,
                        'updated_at' => now(),
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Goals
            |--------------------------------------------------------------------------
            */

            foreach ($validated['goals'] ?? [] as $goal) {

                $targetAmount = (float) $goal['target_amount'];

                $savedAmount = min(
                    (float) $goal['saved_amount'],
                    $targetAmount
                );

                DB::table('goals')->updateOrInsert(
                    [
                        'user_id' => $user->id,
                        'client_id' => $goal['client_id'],
                    ],
                    [
                        'title' => trim($goal['title']),
                        'target_amount' => $targetAmount,
                        'saved_amount' => $savedAmount,
                        'target_date' => $goal['target_date'] ?? null,
                        'is_archived' => $savedAmount >= $targetAmount,
                        'updated_at' => now(),
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Budgets
            |--------------------------------------------------------------------------
            */

            foreach ($validated['budgets'] ?? [] as $budget) {

                DB::table('budgets')->updateOrInsert(
                    [
                        'user_id' => $user->id,
                        'client_id' => $budget['client_id'],
                    ],
                    [
                        'amount' => $budget['amount'],
                        'month' => $budget['month'],
                        'year' => $budget['year'],
                        'updated_at' => now(),
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Settings
            |--------------------------------------------------------------------------
            */

            $settings = [];

            foreach ($validated['settings'] ?? [] as $setting) {
                $settings[$setting['key']] = (bool) $setting['value'];
            }

            if (!empty($settings)) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update($settings);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Guest data migrated successfully.',
        ], 200);
    }
}

