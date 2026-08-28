<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
        |--------------------------------------------------------------------------
        | General API Rate Limiter
        |--------------------------------------------------------------------------
        */

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by(
                    $request->user()?->id
                    ?? $request->ip()
                );
        });


        /*
        |--------------------------------------------------------------------------
        | Authentication Rate Limiter
        |--------------------------------------------------------------------------
        */

        RateLimiter::for('auth', function (Request $request) {
            $email = strtolower(
                trim($request->input('email', ''))
            );

            $response = function (Request $request, array $headers) {
                return response()->json([
                    'message' =>
                        'Too many authentication attempts. Please try again later.',
                ], 429, $headers);
            };

            return [
                // 20 authentication attempts per IP per minute
                Limit::perMinute(20)
                    ->by('ip:' . $request->ip())
                    ->response($response),

                // 10 authentication attempts per email per minute
                Limit::perMinute(10)
                    ->by('email:' . ($email ?: 'unknown'))
                    ->response($response),
            ];
        });


       

/*
|--------------------------------------------------------------------------
| Write API Rate Limiter
|--------------------------------------------------------------------------
*/

RateLimiter::for('write', function (Request $request) {
    return Limit::perMinute(30)
        ->by(
            $request->user()?->id
            ?? $request->ip()
        );
});


 /*
|--------------------------------------------------------------------------
| Expensive API Rate Limiter
|--------------------------------------------------------------------------
*/

RateLimiter::for('expensive', function (Request $request) {
    return Limit::perMinute(15)
        ->by(
            $request->user()?->id
            ?? $request->ip()
        );
});

 /*
|--------------------------------------------------------------------------
| Sensitive Account Rate Limiter
|--------------------------------------------------------------------------
*/

    RateLimiter::for('sensitive', function (Request $request) {
       return Limit::perMinute(5)
        ->by(
            $request->user()?->id
            ?? $request->ip()
        );
        });

        

       /*
|--------------------------------------------------------------------------
| Forgot Password Rate Limiter
|--------------------------------------------------------------------------
*/

RateLimiter::for('password-forgot', function (Request $request) {
    $email = strtolower(
        trim($request->input('email', ''))
    );

    $response = function (Request $request, array $headers) {
        return response()->json([
            'message' =>
                'Too many password reset requests. Please try again later.',

            'remaining' => isset($headers['X-RateLimit-Remaining'])
                ? (int) $headers['X-RateLimit-Remaining']
                : 0,

            'retry_after' => isset($headers['Retry-After'])
                ? (int) $headers['Retry-After']
                : null,
        ], 429, $headers);
    };

    return [
        // 5 forgot-password requests per IP per minute
        Limit::perMinute(5)
            ->by('ip:' . $request->ip())
            ->response($response),

        // 3 forgot-password requests per email per minute
        Limit::perMinute(3)
            ->by('email:' . ($email ?: 'unknown'))
            ->response($response),
    ];
});


/*
|--------------------------------------------------------------------------
| OTP Verification Rate Limiter
|--------------------------------------------------------------------------
*/

RateLimiter::for('password-verify', function (Request $request) {
    $email = strtolower(
        trim($request->input('email', ''))
    );

    $response = function (Request $request, array $headers) {
        return response()->json([
            'message' =>
                'Too many OTP verification attempts. Please try again later.',

            'remaining' => isset($headers['X-RateLimit-Remaining'])
                ? (int) $headers['X-RateLimit-Remaining']
                : 0,

            'retry_after' => isset($headers['Retry-After'])
                ? (int) $headers['Retry-After']
                : null,
        ], 429, $headers);
    };

    return [
        // 10 OTP verification attempts per IP per minute
        Limit::perMinute(10)
            ->by('ip:' . $request->ip())
            ->response($response),

        // 5 OTP verification attempts per email per minute
        Limit::perMinute(5)
            ->by('email:' . ($email ?: 'unknown'))
            ->response($response),
    ];
});


/*
|--------------------------------------------------------------------------
| Password Reset Rate Limiter
|--------------------------------------------------------------------------
*/

RateLimiter::for('password-reset', function (Request $request) {
    $email = strtolower(
        trim($request->input('email', ''))
    );

    $response = function (Request $request, array $headers) {
        return response()->json([
            'message' =>
                'Too many password reset attempts. Please try again later.',

            'remaining' => isset($headers['X-RateLimit-Remaining'])
                ? (int) $headers['X-RateLimit-Remaining']
                : 0,

            'retry_after' => isset($headers['Retry-After'])
                ? (int) $headers['Retry-After']
                : null,
        ], 429, $headers);
    };

    return [
        // 5 password reset attempts per IP per minute
        Limit::perMinute(5)
            ->by('ip:' . $request->ip())
            ->response($response),

        // 3 password reset attempts per email per minute
        Limit::perMinute(3)
            ->by('email:' . ($email ?: 'unknown'))
            ->response($response),
    ];
    });
    }
}