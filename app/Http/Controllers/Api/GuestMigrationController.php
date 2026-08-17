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
            'expenses' => ['nullable', 'array'],
            'goals' => ['nullable', 'array'],
            'budgets' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($validated, $user) {

            /*
            |--------------------------------------------------------------------------
            | Expenses
            |--------------------------------------------------------------------------
            */

            foreach ($validated['expenses'] ?? [] as $expense) {
                DB::table('expenses')->insert([
                    'user_id' => $user->id,

                    'title' => $expense['title'] ?? '',

                    'amount' => $expense['amount'] ?? 0,

                    'category' => $expense['category'] ?? '',

                    'expense_date' => $expense['expense_date'] ?? null,

                    'description' => $expense['description'] ?? null,

                    'created_at' => now(),

                    'updated_at' => now(),
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Goals
            |--------------------------------------------------------------------------
            */

            foreach ($validated['goals'] ?? [] as $goal) {
                DB::table('goals')->insert([
                    'user_id' => $user->id,

                    'title' => $goal['title'] ?? '',

                    'target_amount' => $goal['target_amount'] ?? 0,

                    'saved_amount' => $goal['saved_amount'] ?? 0,

                    'target_date' => $goal['target_date'] ?? null,

                    'is_archived' => $goal['is_archived'] ?? 0,

                    'created_at' => $goal['created_at'] ?? now(),

                    'updated_at' => now(),
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Budgets
            |--------------------------------------------------------------------------
            */

            foreach ($validated['budgets'] ?? [] as $budget) {
                DB::table('budgets')->insert([
                    'user_id' => $user->id,

                    'amount' => $budget['amount'] ?? 0,

                    'month' => $budget['month'] ?? now()->month,

                    'year' => $budget['year'] ?? now()->year,

                    'created_at' => $budget['created_at'] ?? now(),

                    'updated_at' => now(),
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Settings
            |--------------------------------------------------------------------------
            |
            | Settings are stored directly on the users table.
            |
            */

            $settings = [];

            foreach ($validated['settings'] ?? [] as $setting) {
                if (!isset($setting['key'])) {
                    continue;
                }

                $key = $setting['key'];
                $value = $setting['value'] ?? null;

                switch ($key) {
                    case 'daily_reminder':
                        $settings['daily_reminder'] = (bool) $value;
                        break;

                    case 'expense_alerts':
                        $settings['expense_alerts'] = (bool) $value;
                        break;

                    case 'weekly_summary':
                        $settings['weekly_summary'] = (bool) $value;
                        break;

                    case 'dark_mode':
                        $settings['dark_mode'] = (bool) $value;
                        break;

                    case 'notifications_enabled':
                        $settings['notifications_enabled'] = (bool) $value;
                        break;
                }
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
        ]);
    }
}