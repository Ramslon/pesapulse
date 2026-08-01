<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;

class BrevoMailService
{
    public function sendOtpEmail(
        string $email,
        string $name,
        string $otp
    ): bool {

        $html = View::make('emails.otp', [
            'name' => $name,
            'otp' => $otp,
        ])->render();

        $response = Http::withHeaders([
            'api-key' => env('BREVO_API_KEY'),
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', [

            'sender' => [
                'name' => 'PesaPulse',
                'email' => env('MAIL_FROM_ADDRESS'),
            ],

            'to' => [[
                'email' => $email,
                'name' => $name,
            ]],

            'subject' => 'Your PesaPulse Password Reset Code',

            'htmlContent' => $html,
        ]);

        return $response->successful();
    }
}

    
