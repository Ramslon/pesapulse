<?php

namespace App\Services;

use Resend;

class ResendMailService
{
    protected $client;

    public function __construct()
    {
        dd(config('resend.api_key'));
        $this->client = Resend::client(config('resend.api_key'));
    }

   public function sendOtp(string $to, string $name, string $otp)
{
    dd([
        'api_key' => config('resend.api_key'),
        'from' => config('resend.from'),
    ]);
}
}