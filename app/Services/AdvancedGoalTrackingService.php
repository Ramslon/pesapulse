<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AdvancedGoalTrackingService
{
    /**
     * Generate premium advanced goal tracking data.
     *
     * The current database stores the goal's cumulative saved_amount,
     * but not individual contribution history. Therefore the observed
     * saving pace is inferred from saved_amount over the time since
     * goal creation.
     */
    public function analyze(
        User $user,
        ?int $goalId = null,
    ): array {
        $query = $user->goals()
            ->where('is_archived', false)
            ->latest();

        if ($goalId !== null) {
            $query->where('id', $goalId);
        }

        $goals = $query->get();

        $trackedGoals = $goals
            ->map(fn (Goal $goal) => $this->analyzeGoal($goal))
            ->values();

        $summary = $this->buildSummary($trackedGoals);

        $dataQuality = $this->buildDataQuality(
            goals: $goals,
            trackedGoals: $trackedGoals,
        );

        return [
            'as_of' => now()->toDateString(),

            'data_quality' => $dataQuality,

            'summary' => $summary,

            'goals' => $trackedGoals->all(),
        ];
    }

    /**
     * Analyze one goal.
     */
    private function analyzeGoal(Goal $goal): array
    {
        $today = now();

        $target = max(
            0,
            (float) $goal->target_amount
        );

        $saved = max(
            0,
            min(
                (float) $goal->saved_amount,
                $target
            )
        );

        $remaining = max(
            0,
            $target - $saved
        );

        $progressPercentage = $target > 0
            ? round(
                ($saved / $target) * 100,
                2
            )
            : 0;

        $createdAt = $goal->created_at
            ? Carbon::parse($goal->created_at)
            : $today->copy();

        $targetDate = $goal->target_date
            ? Carbon::parse($goal->target_date)
            : null;

        /*
        |--------------------------------------------------------------------------
        | Goal age
        |--------------------------------------------------------------------------
        */

        $elapsedDays = max(
            1,
            $createdAt->diffInDays($today)
        );

        /*
        |--------------------------------------------------------------------------
        | Completion
        |--------------------------------------------------------------------------
        */

        $isCompleted = $remaining <= 0;

        /*
        |--------------------------------------------------------------------------
        | No target date
        |--------------------------------------------------------------------------
        */

        if (!$targetDate) {
            return [
                'id' => $goal->id,
                'title' => $goal->title,

                'target_amount' => round($target, 2),
                'saved_amount' => round($saved, 2),
                'remaining_amount' => round($remaining, 2),
                'progress_percentage' => $progressPercentage,

                'target_date' => null,
                'days_remaining' => null,

                'average_daily_saving_since_creation' =>
                    round($saved / $elapsedDays, 2),

                'required_daily_saving' => null,
                'required_monthly_saving' => null,

                'expected_progress_percentage' => null,
                'progress_variance_percentage' => null,

                'projected_completion_date' => null,

                'forecast_status' => $isCompleted
                    ? 'completed'
                    : 'no_target_date',

                'deadline_risk' => $isCompleted
                    ? 'none'
                    : 'unknown',

                'days_ahead_behind' => null,

                'recommendation' => $isCompleted
                    ? 'Congratulations! You have completed this goal.'
                    : 'Set a target date to unlock deadline tracking and completion forecasting.',

                'milestones' => $this->buildMilestones(
                    target: $target,
                    saved: $saved,
                ),
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Days remaining
        |--------------------------------------------------------------------------
        */

        $remainingDays = (int) max(
            0,
            ceil(
                $today->diffInDays(
                    $targetDate,
                    false
                )
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Original planned duration
        |--------------------------------------------------------------------------
        */

        $totalDays = max(
            1,
            $createdAt->diffInDays($targetDate)
        );

        /*
        |--------------------------------------------------------------------------
        | Expected progress
        |--------------------------------------------------------------------------
        */

        $elapsedForProgress = max(
            0,
            min(
                $createdAt->diffInDays(
                    $today,
                    false
                ),
                $totalDays
            )
        );

        $expectedProgress = (
            $elapsedForProgress /
            $totalDays
        ) * 100;

        $expectedProgress = max(
            0,
            min(
                100,
                $expectedProgress
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Progress variance
        |--------------------------------------------------------------------------
        */

        $progressVariance =
            $progressPercentage -
            $expectedProgress;

        /*
        |--------------------------------------------------------------------------
        | Implied current saving pace
        |--------------------------------------------------------------------------
        */

        $averageDailySaving =
            $saved / $elapsedDays;

        /*
        |--------------------------------------------------------------------------
        | Required saving pace to meet deadline
        |--------------------------------------------------------------------------
        */

        $requiredDailySaving = $remainingDays > 0
            ? $remaining / $remainingDays
            : 0;

        $requiredMonthlySaving =
            round(
                $requiredDailySaving * 30,
                2
            );

        /*
        |--------------------------------------------------------------------------
        | Projected completion
        |--------------------------------------------------------------------------
        */

        $projectedCompletionDate = null;

        if ($isCompleted) {
            $projectedCompletionDate =
                $today->toDateString();
        } elseif ($averageDailySaving > 0) {
            $estimatedDays = (int) ceil(
                $remaining /
                $averageDailySaving
            );

            $projectedCompletionDate =
                $today
                    ->copy()
                    ->addDays($estimatedDays)
                    ->toDateString();
        }

        /*
        |--------------------------------------------------------------------------
        | Forecast status
        |--------------------------------------------------------------------------
        */

        if ($isCompleted) {
            $forecastStatus = 'completed';
        } elseif ($remainingDays <= 0) {
            $forecastStatus = 'overdue';
        } elseif ($progressVariance >= 10) {
            $forecastStatus = 'ahead';
        } elseif ($progressVariance >= -10) {
            $forecastStatus = 'on_track';
        } else {
            $forecastStatus = 'behind';
        }

        /*
        |--------------------------------------------------------------------------
        | Deadline risk
        |--------------------------------------------------------------------------
        */

        if ($isCompleted) {
            $deadlineRisk = 'none';
        } elseif ($remainingDays <= 0) {
            $deadlineRisk = 'high';
        } elseif ($averageDailySaving <= 0) {
            $deadlineRisk = 'high';
        } elseif (
            $projectedCompletionDate !== null &&
            Carbon::parse($projectedCompletionDate)
                ->gt($targetDate)
        ) {
            $deadlineRisk = 'high';
        } elseif ($progressVariance < -10) {
            $deadlineRisk = 'medium';
        } else {
            $deadlineRisk = 'low';
        }

        /*
        |--------------------------------------------------------------------------
        | Days ahead / behind target pace
        |--------------------------------------------------------------------------
        */

        $daysAheadBehind = null;

        if (
            !$isCompleted &&
            $averageDailySaving > 0 &&
            $requiredDailySaving > 0
        ) {
            $projectedDaysRemaining = (
                $remaining /
                $averageDailySaving
            );

            $daysAheadBehind = (int) round(
                $remainingDays -
                $projectedDaysRemaining
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Recommendation
        |--------------------------------------------------------------------------
        */

        $recommendation = $this->buildRecommendation(
            forecastStatus: $forecastStatus,
            deadlineRisk: $deadlineRisk,
            remainingDays: $remainingDays,
            remainingAmount: $remaining,
            requiredMonthlySaving: $requiredMonthlySaving,
        );

        return [
            'id' => $goal->id,
            'title' => $goal->title,

            'target_amount' => round($target, 2),
            'saved_amount' => round($saved, 2),
            'remaining_amount' => round($remaining, 2),
            'progress_percentage' => $progressPercentage,

            'target_date' =>
                $targetDate->toDateString(),

            'days_remaining' => $remainingDays,

            'average_daily_saving_since_creation' =>
                round($averageDailySaving, 2),

            'required_daily_saving' =>
                round($requiredDailySaving, 2),

            'required_monthly_saving' =>
                $requiredMonthlySaving,

            'expected_progress_percentage' =>
                round($expectedProgress, 2),

            'progress_variance_percentage' =>
                round($progressVariance, 2),

            'projected_completion_date' =>
                $projectedCompletionDate,

            'forecast_status' =>
                $forecastStatus,

            'deadline_risk' =>
                $deadlineRisk,

            'days_ahead_behind' =>
                $daysAheadBehind,

            'recommendation' =>
                $recommendation,

            'milestones' => $this->buildMilestones(
                target: $target,
                saved: $saved,
            ),
        ];
    }

    /**
     * Build summary across the selected active goals.
     */
    private function buildSummary(
        Collection $trackedGoals,
    ): array {
        $totalGoals = $trackedGoals->count();

        $completed = $trackedGoals
            ->where('forecast_status', 'completed')
            ->count();

        $ahead = $trackedGoals
            ->where('forecast_status', 'ahead')
            ->count();

        $onTrack = $trackedGoals
            ->where('forecast_status', 'on_track')
            ->count();

        $behind = $trackedGoals
            ->where('forecast_status', 'behind')
            ->count();

        $overdue = $trackedGoals
            ->where('forecast_status', 'overdue')
            ->count();

        $noTargetDate = $trackedGoals
            ->where('forecast_status', 'no_target_date')
            ->count();

        $atRisk = $trackedGoals
            ->whereIn(
                'deadline_risk',
                ['high', 'medium']
            )
            ->count();

        $totalTarget = round(
            (float) $trackedGoals->sum('target_amount'),
            2
        );

        $totalSaved = round(
            (float) $trackedGoals->sum('saved_amount'),
            2
        );

        $totalRemaining = round(
            (float) $trackedGoals->sum('remaining_amount'),
            2
        );

        $overallProgress = $totalTarget > 0
            ? round(
                ($totalSaved / $totalTarget) * 100,
                2
            )
            : 0;

        return [
            'total_goals' => $totalGoals,
            'completed_goals' => $completed,
            'ahead_goals' => $ahead,
            'on_track_goals' => $onTrack,
            'behind_goals' => $behind,
            'overdue_goals' => $overdue,
            'at_risk_goals' => $atRisk,
            'no_target_date_goals' => $noTargetDate,

            'total_target_amount' => $totalTarget,
            'total_saved_amount' => $totalSaved,
            'total_remaining_amount' => $totalRemaining,

            'overall_progress_percentage' =>
                $overallProgress,
        ];
    }

    /**
     * Describe how much data is available for advanced tracking.
     */
    private function buildDataQuality(
        Collection $goals,
        Collection $trackedGoals,
    ): array {
        $totalGoals = $goals->count();

        $goalsWithTargetDates = $goals
            ->filter(
                fn (Goal $goal) =>
                    $goal->target_date !== null
            )
            ->count();

        $goalsWithoutTargetDates =
            $totalGoals - $goalsWithTargetDates;

        $goalsWithSavings = $goals
            ->filter(
                fn (Goal $goal) =>
                    (float) $goal->saved_amount > 0
            )
            ->count();

        return [
            'goals_available' => $totalGoals,
            'goals_with_target_dates' =>
                $goalsWithTargetDates,
            'goals_without_target_dates' =>
                $goalsWithoutTargetDates,
            'goals_with_savings' =>
                $goalsWithSavings,

            'can_calculate_deadline_tracking' =>
                $goalsWithTargetDates > 0,

            'can_calculate_saving_pace' =>
                $goalsWithSavings > 0,

            'pace_basis' =>
                'Cumulative saved amount divided by elapsed days since goal creation.',
        ];
    }

    /**
     * Build milestone information.
     */
    private function buildMilestones(
        float $target,
        float $saved,
    ): array {
        $milestones = [25, 50, 75, 100];

        return array_map(
            function (int $percentage) use ($target, $saved) {
                $amountRequired =
                    round(
                        $target * ($percentage / 100),
                        2
                    );

                return [
                    'percentage' => $percentage,
                    'amount' => $amountRequired,
                    'reached' => $saved >= $amountRequired,
                ];
            },
            $milestones
        );
    }

    /**
     * Generate a goal-specific recommendation.
     */
    private function buildRecommendation(
        string $forecastStatus,
        string $deadlineRisk,
        int $remainingDays,
        float $remainingAmount,
        float $requiredMonthlySaving,
    ): string {
        if ($forecastStatus === 'completed') {
            return 'Congratulations! You have completed this goal.';
        }

        if ($forecastStatus === 'overdue') {
            return 'This goal is past its target date. Increase contributions or update the deadline.';
        }

        if ($deadlineRisk === 'high') {
            return sprintf(
                'Your current saving pace may not meet the deadline. Aim for approximately %.2f per month.',
                $requiredMonthlySaving
            );
        }

        if ($forecastStatus === 'behind') {
            return sprintf(
                'Your progress is behind the expected pace. You need about %.2f per month to meet the deadline.',
                $requiredMonthlySaving
            );
        }

        if ($forecastStatus === 'ahead') {
            return sprintf(
                'You are ahead of schedule. Maintaining your current pace should keep the goal on track.'
            );
        }

        return sprintf(
            'You are on track. About %.2f remains with %d days left.',
            $remainingAmount,
            $remainingDays
        );
    }
}