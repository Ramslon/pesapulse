<?php

namespace App\Services;

use App\Models\User;
use App\Models\PasswordResetOtp;
use Illuminate\Support\Facades\DB;

class AccountDeletionService
{
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {

            // Revoke all API tokens
            $user->tokens()->delete();

            // Remove any pending password reset OTPs
            PasswordResetOtp::where('email', $user->email)->delete();

            /*
             |--------------------------------------------
             | Delete user-owned financial data
             |--------------------------------------------
             */

            $user->expenses()->delete();

            $user->budgets()->delete();

            $user->goals()->delete();

            // Delete the user account
            $user->delete();
        });
    }
}