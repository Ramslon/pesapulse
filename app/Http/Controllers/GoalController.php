<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Goal;


class GoalController extends Controller
{
  public function store(Request $request)
{
    $request->validate([
        'title' => 'required|string|max:255',
        'target_amount' => 'required|numeric|min:1',
        'target_date' => 'nullable|date',
    ]);

    $goal = Goal::create([
        'user_id' => $request->user()->id,
        'title' => $request->title,
        'target_amount' => $request->target_amount,
        'saved_amount' => 0,
        'target_date' => $request->target_date,
    ]);

    return response()->json($goal, 201);
}

public function index(Request $request)
{
    return response()->json(
        $request->user()
            ->goals()
            ->where('is_archived', false)
            ->latest()
            ->get()
    );
}

public function archive(Request $request, Goal $goal)
{
    abort_unless(
        $goal->user_id === $request->user()->id,
        403
    );

    $goal->update([
        'is_archived' => true,
    ]);

    return response()->json([
        'message' => 'Goal archived successfully.',
    ]);
}
 public function progress(Goal $goal)
{
    $percentage = 0;

    if ($goal->target_amount > 0) {
        $percentage = round(
            ($goal->saved_amount / $goal->target_amount) * 100,
            2
        );
    }

    return response()->json([
        'goal' => $goal->title,
        'target_amount' => $goal->target_amount,
        'saved_amount' => $goal->saved_amount,
        'percentage' => $percentage,
    ]);
}

public function updateProgress(Request $request, Goal $goal)
{
    $request->validate([
        'amount' => 'required|numeric|min:1'
    ]);

    $goal->saved_amount += $request->amount;

    $percentage = 0;

    if ($goal->target_amount > 0) {
        $percentage = round(
            ($goal->saved_amount / $goal->target_amount) * 100,
            2
        );
    }

    $milestoneReached = null;

    if (
        $percentage >= 100 &&
        !$goal->milestone_100_notified
    ) {
        $goal->milestone_100_notified = true;

        $milestoneReached = [
            'percentage' => 100,
            'message' =>
                "Congratulations! You've completed your goal."
        ];
    }

    elseif (
        $percentage >= 75 &&
        !$goal->milestone_75_notified
    ) {
        $goal->milestone_75_notified = true;

        $milestoneReached = [
            'percentage' => 75,
            'message' =>
                "Amazing! You've reached 75% of your goal."
        ];
    }

    elseif (
        $percentage >= 50 &&
        !$goal->milestone_50_notified
    ) {
        $goal->milestone_50_notified = true;

        $milestoneReached = [
            'percentage' => 50,
            'message' =>
                "Great progress! You've reached 50% of your goal."
        ];
    }

    elseif (
        $percentage >= 25 &&
        !$goal->milestone_25_notified
    ) {
        $goal->milestone_25_notified = true;

        $milestoneReached = [
            'percentage' => 25,
            'message' =>
                "Nice start! You've reached 25% of your goal."
        ];
    }

    $goal->save();

    return response()->json([
        'message' => 'Goal updated successfully',
        'goal' => $goal,
        'percentage' => $percentage,
        'milestone' => $milestoneReached,
    ]);
}

public function update(Request $request, Goal $goal)
{
    $request->validate([
        'title' => 'sometimes|string|max:255',
        'target_amount' => 'sometimes|numeric|min:1',
        'target_date' => 'nullable|date',
    ]);

    $goal->update($request->only([
        'title',
        'target_amount',
        'target_date',
    ]));

    return response()->json([
        'message' => 'Goal updated successfully',
        'goal' => $goal,
    ]);
}

public function upcomingDeadlines(Request $request)
{
    $goals = $request->user()
        ->goals()
        ->whereNotNull('target_date')
        ->get();

    $alerts = [];

    foreach ($goals as $goal) {

        $daysRemaining = ceil(now()->diffInDays(
            $goal->target_date,
            false
          )
        );

        if ($daysRemaining <= 7 && $daysRemaining >= 0) {
            $alerts[] = [
                'goal_id' => $goal->id,
                'title' => $goal->title,
                'days_remaining' => $daysRemaining,
                'target_date' => $goal->target_date,
            ];
        }
    }

    return response()->json($alerts);
}
public function analytics(Request $request)
{
    $user = $request->user();

    $goals = $user->goals;

    $totalGoals = $goals->count();

    $completedGoals = $goals->filter(function ($goal) {
        return $goal->saved_amount >= $goal->target_amount;
    })->count();

    $activeGoals = $totalGoals - $completedGoals;

    $completionRate = $totalGoals > 0
        ? round(($completedGoals / $totalGoals) * 100, 2)
        : 0;

    return response()->json([
        'total_goals' => $totalGoals,
        'completed_goals' => $completedGoals,
        'active_goals' => $activeGoals,
        'completion_rate' => $completionRate,
    ]);
}

public function insights(Goal $goal)
{
    $targetAmount = $goal->target_amount;
    $savedAmount = $goal->saved_amount;

    $remainingAmount = max(
        0,
        $targetAmount - $savedAmount
    );

    $daysRemaining = 0;

    if ($goal->target_date) {
        $daysRemaining = ceil(now()->diffInDays(
            $goal->target_date,
            false
           )
        );
    }

    $monthlyNeeded = 0;

    if ($daysRemaining > 0) {

        $monthsRemaining =
            max(1, ceil($daysRemaining / 30));

        $monthlyNeeded =
            round(
                $remainingAmount / $monthsRemaining,
                2
            );
    }

    $status = 'healthy';

    if ($daysRemaining <= 30 &&
        $remainingAmount > 0) {
        $status = 'urgent';
    }

    if ($savedAmount >= $targetAmount) {
        $status = 'completed';
    }

    $message = '';

if ($status === 'completed') {
    $message = 'Congratulations! Goal achieved.';
} elseif ($status === 'urgent') {
    $message = 'Increase savings to reach your goal before the deadline.';
} else {
    $message = 'You are on track toward your goal.';
}

    return response()->json([
        'goal' => $goal->title,

        'remaining_amount' =>
            $remainingAmount,

        'days_remaining' =>
            $daysRemaining,

        'monthly_needed' =>
            $monthlyNeeded,

        'status' =>
            $status,
        
        'message' =>
            $message,
    ]);
}

public function forecast(Request $request, Goal $goal)
{
    abort_unless(
        $goal->user_id === $request->user()->id,
        403
    );

    $today = now();

    $targetDate = $goal->target_date
        ? \Carbon\Carbon::parse($goal->target_date)
        : null;

    $saved = $goal->saved_amount;
    $target = $goal->target_amount;

    $remainingAmount = max(0, $target - $saved);

    if (!$targetDate) {
        return response()->json([
            'goal' => $goal->title,
            'forecast' => 'no_target_date',
            'message' => 'Forecast unavailable because no target date was set.',
        ]);
    }

    $totalDays = max(
        1,
        \Carbon\Carbon::parse($goal->created_at)
            ->diffInDays($targetDate)
    );

    $elapsedDays = max(
        1,
        \Carbon\Carbon::parse($goal->created_at)
            ->diffInDays($today)
    );

    $remainingDays = max(
        0,
        ceil($today->diffInDays($targetDate, false))
    );

    $expectedProgress = ($elapsedDays / $totalDays) * 100;
    $actualProgress = ($saved / $target) * 100;

    if ($remainingAmount <= 0) {
        return response()->json([
            'goal' => $goal->title,
            'forecast' => 'completed',
            'message' => 'Congratulations! You have completed this goal.',
            'actual_progress' => round($actualProgress, 2),
            'expected_progress' => round($expectedProgress, 2),
            'remaining_amount' => 0,
            'remaining_days' => 0,
            'estimated_completion_date' => now()->toDateString(),
            'recommended_daily_saving' => 0,
            'recommended_monthly_saving' => 0,
        ]);
    }

    if ($actualProgress >= ($expectedProgress + 10)) {
        $status = 'ahead';
        $message = 'Excellent! You are ahead of schedule.';
    } elseif ($actualProgress >= ($expectedProgress - 10)) {
        $status = 'on_track';
        $message = 'Great! You are on track to reach your goal.';
    } else {
        $status = 'behind';
        $message = 'You need to increase your savings to reach this goal.';
    }

    $dailySavingRate = $saved / $elapsedDays;

    if ($dailySavingRate > 0) {
        $estimatedDays = ceil($remainingAmount / $dailySavingRate);

        $estimatedCompletionDate = now()
            ->addDays($estimatedDays)
            ->toDateString();
    } else {
        $estimatedCompletionDate = null;
    }

    $recommendedDailySaving = $remainingDays > 0
        ? round($remainingAmount / $remainingDays, 2)
        : 0;

    $recommendedMonthlySaving = round(
        $recommendedDailySaving * 30,
        2
    );

    return response()->json([
        'goal' => $goal->title,
        'forecast' => $status,
        'message' => $message,
        'actual_progress' => round($actualProgress, 2),
        'expected_progress' => round($expectedProgress, 2),
        'remaining_amount' => round($remainingAmount, 2),
        'remaining_days' => $remainingDays,
        'estimated_completion_date' => $estimatedCompletionDate,
        'recommended_daily_saving' => $recommendedDailySaving,
        'recommended_monthly_saving' => $recommendedMonthlySaving,
    ]);
}
}

