<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * Return the authenticated user's subscription
     * and available premium entitlements.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $subscription = $user->subscription;

        if (!$subscription) {
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan' => 'basic',
                'status' => 'active',
            ]);
        }

        $isPremium = $subscription->isPremium();

        return response()->json([
            'subscription' => [
                'id' => $subscription->id,
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at?->toISOString(),
                'expires_at' => $subscription->expires_at?->toISOString(),
                'is_premium' => $isPremium,
            ],

            'features' => [
                'advanced_budget_insights' => $isPremium,
                'advanced_analytics' => $isPremium,
                'spending_forecast' => $isPremium,
                'advanced_goal_tracking' => $isPremium,
                'goal_forecast' => $isPremium,
                'budget_simulation' => $isPremium,
                'historical_insights' => $isPremium,
            ],
        ]);
    }
}