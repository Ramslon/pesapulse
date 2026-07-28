<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PasswordResetOtp;
use App\Mail\OtpMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

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

public function forgotPassword(Request $request)
{
    $request->validate([
        'email' => 'required|email',
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json([
            'message' => 'No account found with this email.'
        ], 404);
    }

    // Delete any previous OTPs for this email
    PasswordResetOtp::where('email', $request->email)->delete();

    // Generate a 6-digit OTP
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    PasswordResetOtp::create([
        'email' => $request->email,
        'otp' => $otp,
        'expires_at' => now()->addMinutes(10),
    ]);

    try {

    Mail::to($request->email)->send(new OtpMail($otp));

    return response()->json([
        'message' => 'OTP sent successfully.',
    ]);

} catch (\Exception $e) {

    return response()->json([
        'message' => 'Unable to send OTP email.',
        'error' => $e->getMessage(),
    ], 500);

}
}

public function verifyOtp(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'otp' => 'required|digits:6',
    ]);

    $record = PasswordResetOtp::where('email', $request->email)
        ->where('otp', $request->otp)
        ->first();

    if (!$record) {
        return response()->json([
            'message' => 'Invalid OTP.'
        ], 400);
    }

    if ($record->expires_at->isPast()) {

        $record->delete();

        return response()->json([
            'message' => 'OTP has expired.'
        ], 400);
    }

    return response()->json([
        'message' => 'OTP verified successfully.'
    ]);
}

public function resetPassword(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'otp' => 'required|digits:6',
        'password' => [
            'required',
            'confirmed',
            Password::min(8)
                ->mixedCase()
                ->numbers()
                ->symbols(),
        ],
    ]);

    $record = PasswordResetOtp::where('email', $request->email)
        ->where('otp', $request->otp)
        ->first();

    if (!$record) {
        return response()->json([
            'message' => 'Invalid OTP.'
        ], 400);
    }

    if (now()->greaterThan($record->expires_at)) {

        $record->delete();

        return response()->json([
            'message' => 'OTP has expired.'
        ], 400);
    }

    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json([
            'message' => 'User not found.'
        ], 404);
    }

    $user->update([
        'password' => Hash::make($request->password),
    ]);

    // Delete OTP after successful reset
    $record->delete();

    // Log out every device
    $user->tokens()->delete();

    return response()->json([
        'message' => 'Password reset successfully. Please log in again.',
    ]);
}


}