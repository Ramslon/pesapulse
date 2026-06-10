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
}
