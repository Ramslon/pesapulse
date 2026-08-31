<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PasswordResetOtp;
use App\Services\BrevoMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Services\AccountDeletionService;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request)
{
    $request->merge([
        'email' => strtolower(trim($request->email ?? '')),
        'name' => trim($request->name ?? ''),
    ]);

    $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'password' => [
            'required',
            'confirmed',
            Password::min(8)
                ->mixedCase()
                ->numbers()
                ->symbols(),
        ],
    ]);

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
    ]);

    $token = $user
        ->createToken('auth_token')
        ->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => $user,
    ], 201);
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
         'name' => ['required', 'string', 'max:255'],
         'email' => [
           'required',
           'email',
           'max:255',
           'unique:users,email,' . $user->id,
        ],
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

    $request->validate([
    'daily_reminder' => ['sometimes', 'boolean'],
    'expense_alerts' => ['sometimes', 'boolean'],
    'weekly_summary' => ['sometimes', 'boolean'],
    'dark_mode' => ['sometimes', 'boolean'],
    'notifications_enabled' => ['sometimes', 'boolean'],
    ]);

    $user->update($request->only([
    'daily_reminder',
    'expense_alerts',
    'weekly_summary',
    'dark_mode',
    'notifications_enabled',
    ]));

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

private BrevoMailService $mailService;
private AccountDeletionService $accountDeletionService;

public function __construct(
    BrevoMailService $mailService,
    AccountDeletionService $accountDeletionService
) {
    $this->mailService = $mailService;
    $this->accountDeletionService = $accountDeletionService;
}

public function deleteAccount(Request $request)
{
    $request->validate([
        'password' => 'required',
    ]);

    $user = $request->user();

    if (!Hash::check($request->password, $user->password)) {
        return response()->json([
            'message' => 'Incorrect password.'
        ], 422);
    }

    $this->accountDeletionService->delete($user);

    return response()->json([
        'message' => 'Account deleted successfully.'
    ]);
}

//
public function forgotPassword(Request $request)
{
    $request->validate([
        'email' => ['required', 'email', 'max:255'],
    ]);

    $email = strtolower(trim($request->email));

    $user = User::where('email', $email)->first();

    /*
     * Do not reveal whether the email exists.
     * This prevents account/email enumeration.
     */
    if (!$user) {
        return response()->json([
            'message' =>
                'If an account exists for this email, a verification code has been sent.',
        ]);
    }

    // Remove any previous reset request for this email.
    PasswordResetOtp::where('email', $email)->delete();

    // Generate a cryptographically secure 6-digit OTP.
    $otp = str_pad(
        (string) random_int(0, 999999),
        6,
        '0',
        STR_PAD_LEFT
    );

    $expiresAt = now()->addMinutes(10);

    /*
     * Never store the actual OTP in the database.
     * Only store a hash.
     */
    PasswordResetOtp::create([
        'email' => $email,
        'otp' => Hash::make($otp),
        'expires_at' => $expiresAt,
        'attempts' => 0,
        'verified_at' => null,
    ]);

    $sent = $this->mailService->sendOtpEmail(
        $email,
        $user->name,
        $otp
    );

    /*
     * If email delivery fails, remove the OTP so that
     * an unusable reset record is not left behind.
     */
    if (!$sent) {
        PasswordResetOtp::where('email', $email)->delete();

        return response()->json([
            'message' => 'Unable to send OTP email.',
        ], 500);
    }

    return response()->json([
        'message' => 'OTP sent successfully.',
        'expires_in' => now()->diffInSeconds($expiresAt),
        'resend_after' => 60,
    ]);
}


public function verifyOtp(Request $request)
{
    $request->validate([
        'email' => ['required', 'email', 'max:255'],
        'otp' => ['required', 'digits:6'],
    ]);

    $email = strtolower(trim($request->email));

    $record = PasswordResetOtp::where('email', $email)->first();

    /*
     * Do not reveal whether the email exists or whether
     * the OTP record exists.
     */
    if (!$record) {
        return response()->json([
            'message' =>
                'The verification code is invalid or has expired.',
        ], 400);
    }

    /*
     * Check expiration before checking the OTP.
     */
    if ($record->expires_at->isPast()) {

        $record->delete();

        return response()->json([
            'message' =>
                'Your verification code has expired. Please request a new one.',
        ], 400);
    }

    /*
     * Prevent unlimited OTP guessing.
     */
    if ($record->attempts >= 5) {

        $record->delete();

        return response()->json([
            'message' =>
                'Too many incorrect attempts. Please request a new verification code.',
        ], 429);
    }

    /*
     * Verify the submitted OTP against the stored hash.
     */
    if (!Hash::check($request->otp, $record->otp)) {

        $record->increment('attempts');

        $remainingAttempts = max(
            0,
            5 - $record->attempts
        );

        /*
         * Invalidate the OTP after the fifth failed attempt.
         */
        if ($record->attempts >= 5) {
            $record->delete();

            return response()->json([
                'message' =>
                    'Too many incorrect attempts. Please request a new verification code.',
            ], 429);
        }

        return response()->json([
            'message' =>
                'The verification code you entered is incorrect. Please try again.',
            'remaining_attempts' => $remainingAttempts,
        ], 400);
    }

    /*
     * OTP is valid.
     *
     * Record that verification has actually happened.
     */
    $record->update([
        'verified_at' => now(),
    ]);

    return response()->json([
        'message' => 'OTP verified successfully.',
    ]);
}

public function resetPassword(Request $request)
{
    $request->validate([
        'email' => ['required', 'email', 'max:255'],
        'otp' => ['required', 'digits:6'],
        'password' => [
            'required',
            'confirmed',
            Password::min(8)
                ->mixedCase()
                ->numbers()
                ->symbols(),
        ],
    ]);

    $email = strtolower(trim($request->email));

    $result = DB::transaction(function () use ($request, $email) {

        $record = PasswordResetOtp::where('email', $email)
            ->lockForUpdate()
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'Invalid or expired password reset request.',
            ], 400);
        }

        /*
         * OTP must have been verified first.
         */
        if (!$record->verified_at) {
            return response()->json([
                'message' =>
                    'Please verify your OTP before resetting your password.',
            ], 403);
        }

        /*
         * Verification must still be within the OTP lifetime.
         */
        if ($record->expires_at->isPast()) {

            $record->delete();

            return response()->json([
                'message' =>
                    'OTP has expired. Please request a new one.',
            ], 400);
        }

        /*
         * Verify the OTP again before changing the password.
         */
        if (!Hash::check($request->otp, $record->otp)) {
            return response()->json([
                'message' => 'Invalid verification code.',
            ], 400);
        }

        $user = User::where('email', $email)->first();

        if (!$user) {

            $record->delete();

            return response()->json([
                'message' =>
                    'Password reset request could not be completed.',
            ], 400);
        }

        /*
         * Prevent password reuse.
         */
        if (Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' =>
                    'New password cannot be the same as your current password.',
            ], 422);
        }

        /*
         * Change password.
         */
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        /*
         * Immediately invalidate the OTP.
         */
        $record->delete();

        /*
         * Invalidate every active Sanctum token.
         */
        $user->tokens()->delete();

        return response()->json([
            'message' =>
                'Password reset successfully. Please log in again.',
        ]);
    });

    return $result;
}

}