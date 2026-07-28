<?php

namespace App\Services;

use Resend;

class ResendMailService
{
    protected $client;

    public function __construct()
    {
        $this->client = Resend::client(config('resend.api_key'));
    }

    public function sendOtp(string $to, string $name, string $otp)
    {
        return $this->client->emails->send([
            'from' => config('resend.from_name') . ' <' . config('resend.from') . '>',
            'to' => [$to],
            'subject' => 'PesaPulse • Password Reset Verification Code',

            'html' => view('emails.otp', [
                'name' => $name,
                'otp' => $otp,
            ])->render(),
        ]);
    }
}