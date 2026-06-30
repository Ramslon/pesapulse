<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

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

    public function getProfile(Request $request)
{
    return response()->json([
        'name' => $request->user()->name,
        'email' => $request->user()->email,
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

public function changePassword(Request $request)
{
    $validator = validator($request->all(), [
        'current_password' => ['required'],
        'new_password' => [
            'required',
            'confirmed',
            Password::min(8)
                ->mixedCase()
                ->numbers()
                ->symbols(),
        ],
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422);
    }

    $user = $request->user();

    if (!Hash::check($request->current_password, $user->password)) {
        return response()->json([
            'message' => 'Validation failed.',
            'errors' => [
                'current_password' => [
                    'Current password is incorrect.'
                ]
            ]
        ], 422);
    }

    if (Hash::check($request->new_password, $user->password)) {
        return response()->json([
            'message' => 'Validation failed.',
            'errors' => [
                'new_password' => [
                    'New password cannot be the same as your current password.'
                ]
            ]
        ], 422);
    }

    $user->update([
        'password' => Hash::make($request->new_password),
    ]);

    // Log out all existing devices/sessions
    $user->tokens()->delete();

    return response()->json([
        'message' => 'Password changed successfully. Please log in again.',
    ]);
}


}