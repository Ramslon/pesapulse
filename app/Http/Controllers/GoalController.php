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
            ->latest()
            ->get()
    );
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

        $daysRemaining = now()->diffInDays(
            $goal->target_date,
            false
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
}
