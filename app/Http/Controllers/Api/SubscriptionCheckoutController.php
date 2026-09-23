<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Services\IntaSendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

        $redirectUrl = route(
            'subscription.payment.return',
           ['reference' => $reference]
        );

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
           'metadata' => [
               'checkout_id' => $checkout->id ?? null,
               'checkout_url' => $checkout->url ?? null,
               'redirect_url' => $checkout->redirect_url ?? $redirectUrl,
               'api_ref' => $checkout->api_ref ?? $reference,
               'amount' => $checkout->amount ?? $amount,
               'currency' => $checkout->currency ?? $currency,
               'methods' => $checkout->methods ?? [],
               'host' => $checkout->host ?? null,
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

    public function status(Request $request): JsonResponse
    {
    $validated = $request->validate([
        'reference' => [
            'required',
            'string',
            'max:100',
        ],
    ]);

    $user = $request->user();

    $transaction = PaymentTransaction::query()
        ->where('user_id', $user->id)
        ->where('provider', 'intasend')
        ->where('reference', $validated['reference'])
        ->first();

    if (!$transaction) {
        return response()->json([
            'message' => 'Payment transaction not found.',
            'code' => 'payment_not_found',
        ], 404);
    }

    $subscription = $user->subscription;

    $status = strtolower($transaction->status);

    return response()->json([
        'reference' => $transaction->reference,

        'transaction' => [
            'status' => $status,
            'amount' => (float) $transaction->amount,
            'currency' => $transaction->currency,
            'payment_method' => $transaction->payment_method,
            'paid_at' => $transaction->paid_at?->toISOString(),
        ],

        'subscription' => [
            'plan' => $subscription?->plan ?? 'basic',
            'status' => $subscription?->status ?? 'active',
            'is_premium' => $subscription?->isPremium() ?? false,
            'starts_at' => $subscription?->starts_at?->toISOString(),
            'expires_at' => $subscription?->expires_at?->toISOString(),
        ],

        'status' => $status,
        'is_premium' => $subscription?->isPremium() ?? false,
        'message' => $this->paymentStatusMessage($transaction),
    ]);
  }

    private function paymentStatusMessage(
       PaymentTransaction $transaction
    ): string {
    return match (strtolower($transaction->status)) {
        'complete' =>
            'Payment confirmed. PesaPulse Premium is now active.',

        'processing' =>
            'Your payment is still being processed.',

        'pending' =>
            'Your payment is still being confirmed.',

        'failed' => $this->failedPaymentMessage($transaction),

        default =>
            'The payment status is currently unavailable.',
    };
   }

    private function failedPaymentMessage(
    PaymentTransaction $transaction
    ): string {
    $metadata = is_array($transaction->metadata)
        ? $transaction->metadata
        : [];

    $webhook = is_array($metadata['webhook'] ?? null)
        ? $metadata['webhook']
        : [];

    $reason = strtolower(
        (string) ($webhook['failed_reason'] ?? '')
    );

    if (
        str_contains($reason, 'insufficient') ||
        str_contains($reason, 'balance')
    ) {
        return 'Payment failed due to insufficient funds. Please top up and try again.';
    }

    return 'Payment failed. Please try again.';
    }

    public function paymentReturn(Request $request): Response
{
    $reference = $request->query('reference');

    return response(
        '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>PesaPulse Payment</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background: #f5f7fb;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    margin: 0;
                }

                .card {
                    background: white;
                    padding: 32px;
                    border-radius: 16px;
                    max-width: 480px;
                    width: calc(100% - 40px);
                    box-shadow: 0 10px 30px rgba(0,0,0,.08);
                    text-align: center;
                }

                h1 {
                    margin-bottom: 12px;
                }

                p {
                    color: #666;
                    line-height: 1.6;
                }

                .reference {
                    margin-top: 20px;
                    padding: 12px;
                    background: #f1f3f8;
                    border-radius: 8px;
                    word-break: break-all;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <div class="card">
                <h1>Payment Return</h1>
                <p>
                    You have returned to PesaPulse from the payment page.
                </p>
                <p>
                    Payment confirmation is handled separately by the payment provider webhook.
                    You may now return to the PesaPulse app.
                </p>'
                . (
                    $reference
                        ? '<div class="reference"><strong>Reference:</strong><br>'
                            . e($reference)
                            . '</div>'
                        : ''
                ) .
            '</div>
        </body>
        </html>',
        200,
        ['Content-Type' => 'text/html']
    );
   }
}