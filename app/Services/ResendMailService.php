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
    try {

        return $this->client->emails->send([
            'from' => 'PesaPulse <onboarding@resend.dev>',
            'to' => [$to],
            'subject' => 'Test Email',
            'html' => '<h1>Hello from PesaPulse</h1>',
        ]);

    } catch (\Throwable $e) {

        dd($e->getMessage());

    }
}
}