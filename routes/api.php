<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\ExpenseController;

Route::post('/register', [
    AuthController::class,
    'register'
]);

Route::post('/login', [
    AuthController::class,
    'login'
]);

Route::middleware('auth:sanctum')
    ->group(function () {

    Route::get('/user',
        function (Request $request) {

        return $request->user();
    });

    Route::put('/profile', [
        AuthController::class,
        'updateProfile'
    ]);

    Route::post('/logout', [
        AuthController::class,
        'logout'
    ]);

    Route::get('/expenses/search', [
        ExpenseController::class,
        'search'
    ]);

    Route::get('/analytics', [
        ExpenseController::class,
        'analytics'
    ]);

    Route::get(
        '/dashboard-summary',

        function (Request $request) {

        $user = $request->user();

        return response()->json([

            'total_expenses' =>

                $user->expenses()
                    ->sum('amount'),

            'total_count' =>

                $user->expenses()
                    ->count(),

            'categories' =>

                $user->expenses()
                    ->distinct('category')
                    ->count(),
        ]);
    });

    Route::apiResource(
        'expenses',
        ExpenseController::class
    );
});