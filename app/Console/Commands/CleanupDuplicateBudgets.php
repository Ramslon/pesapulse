<?php

namespace App\Console\Commands;

use App\Models\Budget;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupDuplicateBudgets extends Command
{
    protected $signature = 'budgets:cleanup-duplicates';

    protected $description = 'Remove duplicate budgets while keeping the latest budget for each user/month/year';

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
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('No duplicate budget periods found.');

            return self::SUCCESS;
        }

        foreach ($duplicates as $duplicate) {
            $budgets = Budget::where('user_id', $duplicate->user_id)
                ->where('month', $duplicate->month)
                ->where('year', $duplicate->year)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get();

            $keep = $budgets->first();
            $remove = $budgets->skip(1);

            $this->info(
                "Keeping budget ID {$keep->id} " .
                "({$keep->amount}) for user {$keep->user_id}, " .
                "{$keep->month}/{$keep->year}."
            );

            foreach ($remove as $budget) {
                $this->warn(
                    "Deleting duplicate budget ID {$budget->id} " .
                    "({$budget->amount})."
                );

                $budget->delete();
            }
        }

        $this->info('Duplicate budget cleanup completed.');

        return self::SUCCESS;
    }
}