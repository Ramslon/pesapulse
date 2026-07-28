<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;

Route::get('/test', function () {
    return "PesaPulse is running!";
});

Route::get('/practice', function () {
    $name = "Ramson";
    $expenses = [100, 200, 300];

    return [
        "user" => $name,
        "total" => array_sum($expenses)
    ];
});



Route::get('/mail-test', function () {
    try {
        Mail::raw('Brevo SMTP Test', function ($message) {
            $message->to('ramsonlonayo@gmail.com')
                    ->subject('Brevo Test');
        });

        return 'Mail sent!';
    } catch (\Exception $e) {
        return response()->json([
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], 500);
    }
});

