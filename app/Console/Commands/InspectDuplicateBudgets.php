<?php

namespace App\Console\Commands;

use App\Models\Budget;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InspectDuplicateBudgets extends Command
{
    protected $signature = 'budgets:duplicates';

    protected $description = 'Inspect duplicate budgets for the same user, month and year';

    public function handle(): int
    {
        $duplicates = Budget::query()
            ->select(
                'user_id',
                'month',
                'year',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('user_id', 'month', 'year')
            ->having('total', '>', 1)
            ->orderBy('user_id')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('No duplicate budget periods found.');

            return self::SUCCESS;
        }

        $this->warn('Duplicate budget periods found:');

        $this->table(
            [
                'User ID',
                'Month',
                'Year',
                'Records',
            ],
            $duplicates->map(function ($duplicate) {
                return [
                    $duplicate->user_id,
                    $duplicate->month,
                    $duplicate->year,
                    $duplicate->total,
                ];
            })->toArray()
        );

        $this->newLine();
        $this->info('Detailed records:');

        foreach ($duplicates as $duplicate) {
            $this->newLine();

            $this->line(
                "User {$duplicate->user_id} - " .
                "{$duplicate->month}/{$duplicate->year}"
            );

            $budgets = Budget::where('user_id', $duplicate->user_id)
                ->where('month', $duplicate->month)
                ->where('year', $duplicate->year)
                ->orderBy('id')
                ->get([
                    'id',
                    'user_id',
                    'client_id',
                    'amount',
                    'month',
                    'year',
                    'created_at',
                    'updated_at',
                ]);

            $this->table(
                [
                    'ID',
                    'User',
                    'Client ID',
                    'Amount',
                    'Month',
                    'Year',
                    'Created',
                    'Updated',
                ],
                $budgets->map(function ($budget) {
                    return [
                        $budget->id,
                        $budget->user_id,
                        $budget->client_id ?? 'NULL',
                        $budget->amount,
                        $budget->month,
                        $budget->year,
                        $budget->created_at,
                        $budget->updated_at,
                    ];
                })->toArray()
            );
        }

        return self::SUCCESS;
    }
}