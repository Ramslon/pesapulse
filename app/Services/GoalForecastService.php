<?php

namespace App\Services;

use App\Models\Goal;
use Carbon\Carbon;

class GoalForecastService
{
    private const MIN_FORECAST_DAYS = 7;

    public function forecast(
        $user,
        ?int $goalId = null,
    ): array {
        $query = $user
            ->goals()
            ->where('is_archived', false);

        if ($goalId !== null) {
            $query->whereKey($goalId);
        }

        $goals = $query
            ->orderBy('created_at')
            ->get();

        $asOf = now();

        $forecastedGoals = [];
        $summary = [
            'goals_available' => $goals->count(),
            'goals_forecasted' => 0,
            'completed_goals' => 0,
            'ahead_goals' => 0,
            'on_track_goals' => 0,
            'behind_goals' => 0,
            'overdue_goals' => 0,
            'no_target_date_goals' => 0,
            'insufficient_data_goals' => 0,
            'total_target_amount' => 0,
            'total_saved_amount' => 0,
            'total_remaining_amount' => 0,
        ];

        foreach ($goals as $goal) {
            $result = $this->forecastGoal(
                goal: $goal,
                asOf: $asOf,
            );

            $forecastedGoals[] = $result;

            $summary['total_target_amount'] +=
                $result['target_amount'];

            $summary['total_saved_amount'] +=
                $result['saved_amount'];

            $summary['total_remaining_amount'] +=
                $result['remaining_amount'];

            switch ($result['forecast_status']) {
                case 'completed':
                    $summary['completed_goals']++;
                    break;

                case 'ahead':
                    $summary['ahead_goals']++;
                    $summary['goals_forecasted']++;
                    break;

                case 'on_track':
                    $summary['on_track_goals']++;
                    $summary['goals_forecasted']++;
                    break;

                case 'behind':
                    $summary['behind_goals']++;
                    $summary['goals_forecasted']++;
                    break;

                case 'overdue':
                    $summary['overdue_goals']++;
                    break;

                case 'no_target_date':
                    $summary['no_target_date_goals']++;
                    break;

                case 'insufficient_data':
                    $summary['insufficient_data_goals']++;
                    break;
            }
        }

        $summary['total_target_amount'] =
            round($summary['total_target_amount'], 2);

        $summary['total_saved_amount'] =
            round($summary['total_saved_amount'], 2);

        $summary['total_remaining_amount'] =
            round($summary['total_remaining_amount'], 2);

        $summary['overall_progress_percentage'] =
            $summary['total_target_amount'] > 0
                ? round(
                    (
                        $summary['total_saved_amount'] /
                        $summary['total_target_amount']
                    ) * 100,
                    2
                )
                : 0;

        return [
            'as_of' => $asOf->toDateString(),

            'summary' => $summary,

            'goals' => $forecastedGoals,

            'data_quality' => [
                'goals_available' => $goals->count(),

                'goals_with_target_dates' => $goals
                    ->whereNotNull('target_date')
                    ->count(),

                'goals_with_savings' => $goals
                    ->filter(
                        fn ($goal) =>
                            (float) $goal->saved_amount > 0
                    )
                    ->count(),

                'goals_with_sufficient_history' => $goals
                    ->filter(function ($goal) use ($asOf) {
                        $elapsedDays = $this->elapsedDays(
                            $goal,
                            $asOf,
                        );

                        return $elapsedDays >= self::MIN_FORECAST_DAYS;
                    })
                    ->count(),

                'can_forecast' => $goals->contains(
                    fn ($goal) =>
                        $this->canForecast($goal, $asOf)
                ),

                'pace_basis' =>
                    'Cumulative saved amount divided by elapsed days since goal creation.',

                'minimum_forecast_history_days' =>
                    self::MIN_FORECAST_DAYS,

                'note' =>
                    'Forecast confidence is reduced for goals with limited saving history because the current goal schema stores cumulative savings rather than individual savings transactions.',
            ],
        ];
    }

    private function forecastGoal(
        Goal $goal,
        Carbon $asOf,
    ): array {
        $targetAmount = max(
            0,
            (float) $goal->target_amount
        );

        $savedAmount = max(
            0,
            (float) $goal->saved_amount
        );

        $remainingAmount = max(
            0,
            $targetAmount - $savedAmount
        );

        $progressPercentage = $targetAmount > 0
            ? round(
                min(
                    100,
                    ($savedAmount / $targetAmount) * 100
                ),
                2
            )
            : 0;

        $createdAt = $goal->created_at
            ? Carbon::parse($goal->created_at)
            : $asOf->copy();

        $elapsedDays = $this->elapsedDays(
            $goal,
            $asOf,
        );

        $targetDate = $goal->target_date
            ? Carbon::parse($goal->target_date)->startOfDay()
            : null;

        $daysRemaining = $targetDate !== null
            ? max(
                0,
                $asOf->copy()->startOfDay()->diffInDays(
                    $targetDate,
                    false
                )
            )
            : null;

        $requiredDailySaving =
            $daysRemaining !== null &&
            $daysRemaining > 0 &&
            $remainingAmount > 0
                ? round(
                    $remainingAmount / $daysRemaining,
                    2
                )
                : 0;

        $requiredMonthlySaving =
            round(
                $requiredDailySaving * 30,
                2
            );

        $currentDailySaving = $elapsedDays > 0
            ? round(
                $savedAmount / $elapsedDays,
                2
            )
            : 0;

        /*
         * A completed goal does not need a future forecast.
         */
        if ($remainingAmount <= 0) {
            return [
                'id' => $goal->id,
                'title' => $goal->title,

                'target_amount' => round($targetAmount, 2),
                'saved_amount' => round($savedAmount, 2),
                'remaining_amount' => 0,

                'progress_percentage' => $progressPercentage,

                'created_at' =>
                    $createdAt->toDateString(),

                'target_date' =>
                    $targetDate?->toDateString(),

                'days_elapsed' => $elapsedDays,
                'days_remaining' => $daysRemaining,

                'current_daily_saving' =>
                    $currentDailySaving,

                'required_daily_saving' =>
                    $requiredDailySaving,

                'required_monthly_saving' =>
                    $requiredMonthlySaving,

                'projected_completion_date' =>
                    $goal->completed_at
                        ? Carbon::parse(
                            $goal->completed_at
                        )->toDateString()
                        : $asOf->toDateString(),

                'forecast_status' => 'completed',

                'confidence' => 'high',

                'confidence_message' =>
                    'This goal has already been completed.',

                'days_ahead_behind' => 0,

                'scenarios' => $this->emptyCompletedScenarios(),

                'recommendation' =>
                    'Goal completed. No further saving is required for this target.',

                'data_quality' => [
                    'has_target_date' => $targetDate !== null,
                    'has_savings' => $savedAmount > 0,
                    'elapsed_days' => $elapsedDays,
                    'can_forecast' => false,
                ],
            ];
        }

        /*
         * A target date is useful for deadline comparison, but
         * the future completion date can still be estimated
         * for goals without a target date once there is enough
         * savings history.
         */
        if ($elapsedDays < self::MIN_FORECAST_DAYS) {
            return [
                'id' => $goal->id,
                'title' => $goal->title,

                'target_amount' => round($targetAmount, 2),
                'saved_amount' => round($savedAmount, 2),
                'remaining_amount' =>
                    round($remainingAmount, 2),

                'progress_percentage' =>
                    $progressPercentage,

                'created_at' =>
                    $createdAt->toDateString(),

                'target_date' =>
                    $targetDate?->toDateString(),

                'days_elapsed' => $elapsedDays,
                'days_remaining' => $daysRemaining,

                'current_daily_saving' =>
                    $currentDailySaving,

                'required_daily_saving' =>
                    $requiredDailySaving,

                'required_monthly_saving' =>
                    $requiredMonthlySaving,

                'projected_completion_date' => null,

                'forecast_status' => 'insufficient_data',

                'confidence' => 'insufficient_data',

                'confidence_message' =>
                    'There is not enough saving history to produce a reliable completion forecast. Keep updating this goal for at least seven days.',

                'days_ahead_behind' => null,

                'scenarios' => null,

                'recommendation' =>
                    'Continue recording savings progress so PesaPulse can establish a more meaningful saving pace.',

                'data_quality' => [
                    'has_target_date' =>
                        $targetDate !== null,

                    'has_savings' =>
                        $savedAmount > 0,

                    'elapsed_days' =>
                        $elapsedDays,

                    'can_forecast' => false,

                    'pace_basis' =>
                        'Cumulative saved amount divided by elapsed days since goal creation.',
                ],
            ];
        }

        if ($currentDailySaving <= 0) {
            return [
                'id' => $goal->id,
                'title' => $goal->title,

                'target_amount' =>
                    round($targetAmount, 2),

                'saved_amount' =>
                    round($savedAmount, 2),

                'remaining_amount' =>
                    round($remainingAmount, 2),

                'progress_percentage' =>
                    $progressPercentage,

                'created_at' =>
                    $createdAt->toDateString(),

                'target_date' =>
                    $targetDate?->toDateString(),

                'days_elapsed' => $elapsedDays,
                'days_remaining' => $daysRemaining,

                'current_daily_saving' => 0,
                'required_daily_saving' =>
                    $requiredDailySaving,

                'required_monthly_saving' =>
                    $requiredMonthlySaving,

                'projected_completion_date' => null,

                'forecast_status' =>
                    $targetDate !== null &&
                    $targetDate->isPast()
                        ? 'overdue'
                        : 'insufficient_data',

                'confidence' => 'insufficient_data',

                'confidence_message' =>
                    'No positive saving pace has been recorded yet, so PesaPulse cannot project a completion date.',

                'days_ahead_behind' => null,

                'scenarios' => null,

                'recommendation' =>
                    $targetDate !== null &&
                    $targetDate->isPast()
                        ? 'The target date has passed and no saving pace is available for a future completion estimate.'
                        : 'Add savings to this goal so PesaPulse can establish a completion forecast.',

                'data_quality' => [
                    'has_target_date' =>
                        $targetDate !== null,

                    'has_savings' =>
                        $savedAmount > 0,

                    'elapsed_days' =>
                        $elapsedDays,

                    'can_forecast' => false,

                    'pace_basis' =>
                        'Cumulative saved amount divided by elapsed days since goal creation.',
                ],
            ];
        }

        $projectedDays = (int) ceil(
            $remainingAmount / $currentDailySaving
        );

        $projectedCompletionDate = $asOf
            ->copy()
            ->startOfDay()
            ->addDays($projectedDays);

        $forecastStatus =
            $this->determineStatus(
                targetDate: $targetDate,
                projectedCompletionDate: $projectedCompletionDate,
                asOf: $asOf,
                remainingAmount: $remainingAmount,
            );

        $confidence =
            $this->confidenceLevel(
                elapsedDays: $elapsedDays,
            );

        $scenarios =
            $this->buildScenarios(
                remainingAmount: $remainingAmount,
                currentDailySaving: $currentDailySaving,
                asOf: $asOf,
                targetDate: $targetDate,
            );

        $daysAheadBehind =
            $targetDate !== null
                ? $projectedCompletionDate
                    ->diffInDays(
                        $targetDate,
                        false
                    )
                : null;

        return [
            'id' => $goal->id,
            'title' => $goal->title,

            'target_amount' =>
                round($targetAmount, 2),

            'saved_amount' =>
                round($savedAmount, 2),

            'remaining_amount' =>
                round($remainingAmount, 2),

            'progress_percentage' =>
                $progressPercentage,

            'created_at' =>
                $createdAt->toDateString(),

            'target_date' =>
                $targetDate?->toDateString(),

            'days_elapsed' =>
                $elapsedDays,

            'days_remaining' =>
                $daysRemaining,

            'current_daily_saving' =>
                $currentDailySaving,

            'required_daily_saving' =>
                $requiredDailySaving,

            'required_monthly_saving' =>
                $requiredMonthlySaving,

            'projected_completion_date' =>
                $projectedCompletionDate->toDateString(),

            'forecast_status' =>
                $forecastStatus,

            'confidence' =>
                $confidence['level'],

            'confidence_message' =>
                $confidence['message'],

            'days_ahead_behind' =>
                $daysAheadBehind,

            'scenarios' =>
                $scenarios,

            'recommendation' =>
                $this->recommendation(
                    status: $forecastStatus,
                    targetDate: $targetDate,
                    projectedCompletionDate:
                        $projectedCompletionDate,
                    currentDailySaving:
                        $currentDailySaving,
                    requiredDailySaving:
                        $requiredDailySaving,
                ),

            'data_quality' => [
                'has_target_date' =>
                    $targetDate !== null,

                'has_savings' =>
                    $savedAmount > 0,

                'elapsed_days' =>
                    $elapsedDays,

                'can_forecast' => true,

                'pace_basis' =>
                    'Cumulative saved amount divided by elapsed days since goal creation.',
            ],
        ];
    }

    private function elapsedDays(
        Goal $goal,
        Carbon $asOf,
    ): int {
        $createdAt = $goal->created_at
            ? Carbon::parse($goal->created_at)
            : $asOf->copy();

        return max(
            1,
            $createdAt
                ->startOfDay()
                ->diffInDays(
                    $asOf->copy()->startOfDay()
                )
        );
    }

    private function canForecast(
        Goal $goal,
        Carbon $asOf,
    ): bool {
        if ((float) $goal->saved_amount <= 0) {
            return false;
        }

        return $this->elapsedDays(
            goal: $goal,
            asOf: $asOf,
        ) >= self::MIN_FORECAST_DAYS;
    }

    private function confidenceLevel(
        int $elapsedDays,
    ): array {
        if ($elapsedDays >= 90) {
            return [
                'level' => 'high',
                'message' =>
                    'The forecast is based on at least 90 days of cumulative saving history.',
            ];
        }

        if ($elapsedDays >= 30) {
            return [
                'level' => 'medium',
                'message' =>
                    'The forecast is based on at least 30 days of cumulative saving history and should still be treated as an estimate.',
            ];
        }

        return [
            'level' => 'low',
            'message' =>
                'The forecast is available, but the goal has less than 30 days of saving history, so the estimated pace may change.',
        ];
    }

    private function determineStatus(
        ?Carbon $targetDate,
        Carbon $projectedCompletionDate,
        Carbon $asOf,
        float $remainingAmount,
    ): string {
        if ($remainingAmount <= 0) {
            return 'completed';
        }

        if (
            $targetDate !== null &&
            $targetDate->isPast()
        ) {
            return 'overdue';
        }

        if ($targetDate === null) {
            return 'on_track';
        }

        if (
            $projectedCompletionDate
                ->lessThanOrEqualTo($targetDate)
        ) {
            $daysAhead = $projectedCompletionDate
                ->diffInDays($targetDate, false);

            return $daysAhead >= 7
                ? 'ahead'
                : 'on_track';
        }

        return 'behind';
    }

    private function buildScenarios(
        float $remainingAmount,
        float $currentDailySaving,
        Carbon $asOf,
        ?Carbon $targetDate,
    ): array {
        $definitions = [
            'current' => 1.00,
            'slower' => 0.90,
            'faster' => 1.10,
        ];

        $scenarios = [];

        foreach ($definitions as $name => $multiplier) {
            $dailySaving = round(
                $currentDailySaving * $multiplier,
                2
            );

            if ($dailySaving <= 0) {
                $scenarios[$name] = [
                    'daily_saving' => 0,
                    'completion_date' => null,
                    'meets_target_date' => null,
                ];

                continue;
            }

            $days = (int) ceil(
                $remainingAmount / $dailySaving
            );

            $completionDate = $asOf
                ->copy()
                ->startOfDay()
                ->addDays($days);

            $meetsTargetDate = $targetDate !== null
                ? $completionDate
                    ->lessThanOrEqualTo($targetDate)
                : null;

            $scenarios[$name] = [
                'daily_saving' => $dailySaving,
                'completion_date' =>
                    $completionDate->toDateString(),
                'days_to_completion' => $days,
                'meets_target_date' =>
                    $meetsTargetDate,
            ];
        }

        return $scenarios;
    }

    private function emptyCompletedScenarios(): array
    {
        return [
            'current' => [
                'daily_saving' => 0,
                'completion_date' => null,
                'days_to_completion' => 0,
                'meets_target_date' => true,
            ],
            'slower' => [
                'daily_saving' => 0,
                'completion_date' => null,
                'days_to_completion' => 0,
                'meets_target_date' => true,
            ],
            'faster' => [
                'daily_saving' => 0,
                'completion_date' => null,
                'days_to_completion' => 0,
                'meets_target_date' => true,
            ],
        ];
    }

    private function recommendation(
        string $status,
        ?Carbon $targetDate,
        Carbon $projectedCompletionDate,
        float $currentDailySaving,
        float $requiredDailySaving,
    ): string {
        return match ($status) {
            'ahead' =>
                'You are currently ahead of the projected deadline. Maintaining your current saving pace should keep the goal on track.',

            'on_track' =>
                'Your current saving pace is consistent with the target date. Continue saving regularly.',

            'behind' =>
                $requiredDailySaving > 0
                    ? sprintf(
                        'Your current pace is below the pace needed for the target date. Aim for at least KES %s per day to close the gap.',
                        number_format(
                            $requiredDailySaving,
                            2
                        )
                    )
                    : 'Increase your saving pace to reach the target date.',

            'overdue' =>
                'The target date has passed while the goal still has money remaining. Increase your saving pace or review the deadline.',

            'completed' =>
                'This goal has been completed.',

            'no_target_date' =>
                'Continue saving consistently. Set a target date to make the forecast more actionable.',

            default =>
                'Continue recording savings progress so PesaPulse can build a more reliable forecast.',
        };
    }
}