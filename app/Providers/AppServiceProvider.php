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
                trim(
                    $request->input('email', '')
                )
            );

            return [
                Limit::perMinute(20)
                    ->by('ip:' . $request->ip()),

                Limit::perMinute(10)
                    ->by('email:' . ($email ?: 'unknown'))
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'message' =>
                                'Too many authentication attempts. Please try again later.',
                        ], 429, $headers);
                    }),
            ];
        });
    }
}