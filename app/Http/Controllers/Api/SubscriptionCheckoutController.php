<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Services\IntaSendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class SubscriptionCheckoutController extends Controller
{
    public function __construct(
        private readonly IntaSendService $intaSend,
    ) {
    }

    /**
     * Create a PesaPulse Premium checkout session.
     */
    public function create(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Authentication required.',
            ], 401);
        }

        $subscription = $user->subscription;

        if ($subscription?->isPremium()) {
            return response()->json([
                'message' => 'You already have an active PesaPulse Premium subscription.',
                'code' => 'already_premium',
            ], 409);
        }

        $amount = (float) config(
            'services.intasend.premium_amount'
        );

        $currency = config(
            'services.intasend.premium_currency',
            'KES'
        );

        if ($amount <= 0) {
            return response()->json([
                'message' => 'Premium subscription pricing is not configured.',
                'code' => 'premium_price_not_configured',
            ], 500);
        }

        $reference = 'PP-' . Str::upper(
            Str::random(20)
        );

        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'provider' => 'intasend',
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
        ]);

        $nameParts = preg_split(
            '/\s+/',
            trim($user->name),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        $firstName = $nameParts[0] ?? 'PesaPulse';

        $lastName = count($nameParts) > 1
            ? implode(' ', array_slice($nameParts, 1))
            : 'User';

        try {
            $checkout = $this->intaSend->createCheckout(
                amount: $amount,
                currency: $currency,
                apiReference: $reference,
                firstName: $firstName,
                lastName: $lastName,
                email: $user->email,
            );

            $transaction->update([
                'provider_transaction_id' =>
                    $checkout->invoice_id ?? null,
                'metadata' => [
                    'checkout_response' => $this->normalizeResponse(
                        $checkout
                    ),
                ],
            ]);

            return response()->json([
                'message' => 'Premium checkout created successfully.',
                'reference' => $reference,
                'checkout_url' => $checkout->url ?? null,
                'amount' => $amount,
                'currency' => $currency,
            ], 201);
        } catch (Throwable $e) {
            report($e);

            $transaction->update([
                'status' => 'failed',
                'metadata' => [
                    'error' => $e->getMessage(),
                ],
            ]);

            return response()->json([
                'message' => 'Unable to create Premium checkout.',
                'code' => 'checkout_creation_failed',
            ], 502);
        }
    }

    private function normalizeResponse(object $response): array
    {
        return json_decode(
            json_encode($response),
            true
        ) ?? [];
    }
}