<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetOtp extends Model
{
   protected $fillable = [
    'email',
    'otp',
    'attempts',
    'expires_at',
    'verified_at',
     ];

    protected $casts = [
    'expires_at' => 'datetime',
    'verified_at' => 'datetime',
    ];
}