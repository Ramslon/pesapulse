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
    Mail::raw('This is a Gmail SMTP test from PesaPulse.', function ($message) {
        $message->to('ramsonlonayo@gmail.com')
                ->subject('SMTP Test');
    });

    return 'Mail sent!';
});

