<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;


use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\Api\GuestMigrationController;
use Illuminate\Support\Facades\DB;

Route::middleware('throttle:auth')->group(function () {

    Route::post('/register', [AuthController::class, 'register']);

    Route::post('/login', [AuthController::class, 'login']);

});

Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:password-forgot');

Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])
    ->middleware('throttle:password-verify');

Route::post('/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('throttle:password-reset');

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
    ]);
});

Route::get('/diagnostic-public', function () {
    return response()->json([
        'status' => 'ok',
    ]);
});

Route::get('/diagnostic-sanctum', function (Request $request) {
    return response()->json([
        'status' => 'ok',
        'user_id' => $request->user()->id,
    ]);
})->middleware('auth:sanctum');

Route::get('/diagnostic-throttle', function () {
    return response()->json([
        'status' => 'ok',
    ]);
})->middleware('throttle:api');

Route::get('/diagnostic-db', function () {
    $startedAt = microtime(true);

    // First query.
    $firstStartedAt = microtime(true);

    $first = DB::select('SELECT 1 AS test');

    $firstMs = round(
        (microtime(true) - $firstStartedAt) * 1000,
        2
    );

    // Second query using the same Laravel database connection.
    $secondStartedAt = microtime(true);

    $second = DB::select('SELECT 1 AS test');

    $secondMs = round(
        (microtime(true) - $secondStartedAt) * 1000,
        2
    );

    // Third query.
    $thirdStartedAt = microtime(true);

    $third = DB::select('SELECT 1 AS test');

    $thirdMs = round(
        (microtime(true) - $thirdStartedAt) * 1000,
        2
    );

    $totalMs = round(
        (microtime(true) - $startedAt) * 1000,
        2
    );

    return response()->json([
        'status' => 'ok',

        'results' => [
            'first' => $first,
            'second' => $second,
            'third' => $third,
        ],

        '_diagnostics' => [
            'first_query_ms' => $firstMs,
            'second_query_ms' => $secondMs,
            'third_query_ms' => $thirdMs,
            'total_ms' => $totalMs,
        ],
    ]);
});

Route::middleware(['auth:sanctum', 'throttle:api'])
    ->group(function () {


    Route::get('/diagnostic-auth', function (Request $request) {
    return response()->json([
        'status' => 'ok',
        'user_id' => $request->user()->id,
    ]);
    });

    Route::get('/user', function (Request $request) {
    $user = $request->user();

    return response()->json([
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
    ]);
    });

    Route::get('/profile', [AuthController::class, 'getProfile']);

    Route::put('/profile', [
    AuthController::class,
    'updateProfile'
    ])->middleware('throttle:write');


    Route::post('/logout', [AuthController::class, 'logout'])
      ->middleware('throttle:write');

    Route::delete('/delete-account', [AuthController::class, 'deleteAccount'])  
      ->middleware('throttle:sensitive');

    Route::get('/expenses/search', [
    ExpenseController::class,
    'search'
    ])->middleware('throttle:expensive');

    Route::get('/analytics', [
    ExpenseController::class,
    'analytics'
    ])->middleware('throttle:expensive');

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

    Route::get('/dashboard', [
    ExpenseController::class,
    'dashboard'
    ]);

    Route::apiResource(
        'expenses',
        ExpenseController::class
    );

    Route::put('/preferences', [AuthController::class, 'updatePreferences'])
    ->middleware('throttle:write');

    Route::get('/preferences', [AuthController::class, 'getPreferences']);

    Route::put('/change-password', [AuthController::class, 'changePassword'])  
      ->middleware('throttle:sensitive');

    Route::post('/budget', [BudgetController::class, 'store'])
    ->middleware('throttle:write');

    Route::delete('/budget', [BudgetController::class, 'destroy'])
    ->middleware('throttle:write');

    Route::get('/financial-insights', [BudgetController::class,'financialInsights'])
      ->middleware('throttle:expensive');

    Route::get('/budget-summary', [BudgetController::class, 'summary']);

    
    Route::post('/goals', [GoalController::class, 'store'])
      ->middleware('throttle:write');

    Route::get('/goals', [GoalController::class, 'index']);

    Route::get('/goals/derived-data', [GoalController::class, 'derivedData']);

    Route::get( '/goals/upcoming-deadlines', [GoalController::class, 'upcomingDeadlines']);

    Route::get('/goals/analytics', [GoalController::class, 'analytics'])
      ->middleware('throttle:expensive');

    Route::get('/goals/{goal}/progress', [GoalController::class, 'progress']);

    Route::put('/goals/{goal}/progress', [GoalController::class, 'updateProgress']) 
      ->middleware('throttle:write');

    Route::get('/goals/{goal}/insights', [GoalController::class, 'insights']) 
      ->middleware('throttle:expensive');

    Route::get('/goals/{goal}/forecast', [GoalController::class, 'forecast'])
      ->middleware('throttle:expensive');

    Route::get('/goals/archived', [GoalController::class, 'archived']);

    Route::put('/goals/{goal}', [GoalController::class, 'update'])
      ->middleware('throttle:write');

    Route::put('/goals/{goal}/archive', [GoalController::class, 'archive'])
      ->middleware('throttle:write');

    Route::put('/goals/{goal}/restore', [GoalController::class, 'restore'])
      ->middleware('throttle:write');

    Route::delete('/goals/{goal}', [GoalController::class, 'destroy'])
      ->middleware('throttle:write');

    Route::post('/guest/migrate', [
    GuestMigrationController::class,
    'migrate',
    ])->middleware('throttle:sensitive');
});