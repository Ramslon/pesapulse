<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {

        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6'
        ]);

        $user = User::create([
            'name'=> $request->name,
            'email'=> $request->email,

            // better practice
            'password'=> Hash::make($request->password),
        ]);

        $token = $user
            ->createToken('auth_token')
            ->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }

    public function login(Request $request)
    {

        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where(
            'email',
            $request->email
        )->first();

        if (
            !$user ||
            !Hash::check(
                $request->password,
                $user->password
            )
        ) {

            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        $token = $user
            ->createToken('auth_token')
            ->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {

        $request->user()
            ->currentAccessToken()
            ->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

    return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    public function updatePreferences(Request $request)
{
    $user = $request->user();

    $user->update([
        'daily_reminder' => $request->daily_reminder,
        'expense_alerts' => $request->expense_alerts,
        'weekly_summary' => $request->weekly_summary,

        'dark_mode' => $request->dark_mode,
        'notifications_enabled' => $request->notifications_enabled,
    ]);

    return response()->json([
        'message' => 'Preferences updated successfully'
    ]);
}

public function getPreferences(Request $request)
{
    return response()->json([
        'daily_reminder' => $request->user()->daily_reminder,
        'expense_alerts' => $request->user()->expense_alerts,
        'weekly_summary' => $request->user()->weekly_summary,

        'dark_mode' => $request->user()->dark_mode,
        'notifications_enabled' => $request->user()->notifications_enabled,
    ]);
}
}