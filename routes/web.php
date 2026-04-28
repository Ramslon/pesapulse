<?php

use Illuminate\Support\Facades\Route;

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
