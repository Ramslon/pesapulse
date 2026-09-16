<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePremiumSubscription
{
    /**
     * Handle an incoming request.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Authentication required.',
            ], 401);
        }

        $subscription = $user->subscription;

        if (!$subscription || !$subscription->isPremium()) {
            return response()->json([
                'message' => 'This feature requires an active PesaPulse Premium subscription.',
                'code' => 'premium_required',
                'subscription' => [
                    'plan' => $subscription?->plan ?? 'basic',
                    'status' => $subscription?->status ?? 'active',
                    'is_premium' => false,
                ],
            ], 403);
        }

        return $next($request);
    }
}