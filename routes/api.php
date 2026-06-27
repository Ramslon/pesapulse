<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;


use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GoalController;

Route::post('/register', [
    AuthController::class,
    'register'
]);

Route::post('/login', [
    AuthController::class,
    'login'
]);

Route::get('/test', function () {
    return response()->json([
        'message' => 'API works'
    ]);
});

Route::get('/env-check', function () {
    return [
        'host' => env('DB_HOST'),
        'port' => env('DB_PORT'),
    ];
});

Route::middleware('auth:sanctum')
    ->group(function () {

    Route::get('/user',
        function (Request $request) {

        return $request->user();
    });

    Route::get('/profile', [AuthController::class, 'getProfile']);

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

    Route::put('/preferences', [AuthController::class, 'updatePreferences']);

    Route::get('/preferences', [AuthController::class, 'getPreferences']);

    Route::post('/budget', [BudgetController::class, 'store']);

    Route::get('/financial-insights',[BudgetController::class, 'financialInsights']);

    Route::get('/budget-summary', [BudgetController::class, 'summary']);

    Route::post('/goals', [GoalController::class, 'store']);

    Route::get('/goals', [GoalController::class, 'index']);

    Route::get( '/goals/upcoming-deadlines', [GoalController::class, 'upcomingDeadlines']);

    Route::get('/goals/analytics', [GoalController::class, 'analytics']);

    Route::get('/goals/{goal}/progress', [GoalController::class, 'progress']);

    Route::put('/goals/{goal}/progress', [GoalController::class, 'updateProgress']);

    Route::get('/goals/{goal}/insights', [GoalController::class, 'insights']);

    Route::get('/goals/{goal}/forecast', [GoalController::class, 'forecast']);

    Route::put('/goals/{goal}', [GoalController::class, 'update']);

    Route::put('/goals/{goal}/archive', [GoalController::class, 'archive']);

    Route::get('/goals/archived', [GoalController::class, 'archived']);

 
});