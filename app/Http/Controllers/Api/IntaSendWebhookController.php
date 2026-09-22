<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class IntaSendWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        $configuredChallenge = (string) config(
            'services.intasend.webhook_challenge'
        );

        $receivedChallenge = (string) (
            $payload['challenge'] ?? ''
        );

        if (
            $configuredChallenge === '' ||
            !hash_equals(
                $configuredChallenge,
                $receivedChallenge
            )
        ) {
            Log::warning('Invalid IntaSend webhook challenge.');

            return response()->json([
                'message' => 'Invalid webhook challenge.',
            ], 403);
        }

        $apiReference = trim(
            (string) ($payload['api_ref'] ?? '')
        );

        if ($apiReference === '') {
            Log::warning(
                'IntaSend webhook missing api_ref.',
                [
                    'payload' => $this->safePayload($payload),
                ]
            );

            return response()->json([
                'message' => 'Missing payment reference.',
            ], 422);
        }

        $state = strtoupper(
            trim((string) ($payload['state'] ?? ''))
        );

        try {
            return DB::transaction(function () use (
                $payload,
                $apiReference,
                $state
            ) {
                $transaction = PaymentTransaction::query()
                    ->where('provider', 'intasend')
                    ->where('reference', $apiReference)
                    ->lockForUpdate()
                    ->first();

                if (!$transaction) {
                    Log::warning(
                        'IntaSend webhook reference not found.',
                        [
                            'api_ref' => $apiReference,
                        ]
                    );

                    return response()->json([
                        'message' => 'Payment reference not found.',
                    ], 404);
                }

                $this->validatePaymentDetails(
                    $transaction,
                    $payload
                );

                $invoiceId = $this->nullableString(
                    $payload['invoice_id'] ?? null
                );

                $provider = $this->nullableString(
                    $payload['provider'] ?? null
                );

                $metadata = is_array($transaction->metadata)
                    ? $transaction->metadata
                    : [];

                $metadata['webhook'] = [
                    'state' => $state,
                    'invoice_id' => $invoiceId,
                    'provider' => $provider,
                    'failed_reason' => $payload['failed_reason'] ?? null,
                    'failed_code' => $payload['failed_code'] ?? null,
                    'received_at' => now()->toISOString(),
                ];

                /*
                 * Idempotency:
                 * A COMPLETE transaction has already been processed.
                 */
                if (
                    $state === 'COMPLETE' &&
                    $transaction->status === 'complete'
                ) {
                    return response()->json([
                        'message' => 'Payment already processed.',
                        'reference' => $transaction->reference,
                    ]);
                }

                $transaction->provider_transaction_id =
                    $invoiceId ?? $transaction->provider_transaction_id;

                $transaction->payment_method =
                    $provider ?? $transaction->payment_method;

                $transaction->metadata = $metadata;

                switch ($state) {
                    case 'PENDING':
                        $transaction->status = 'pending';
                        break;

                    case 'PROCESSING':
                        $transaction->status = 'processing';
                        break;

                    case 'FAILED':
                        $transaction->status = 'failed';
                        break;

                    case 'COMPLETE':
                        $transaction->status = 'complete';
                        $transaction->paid_at = now();

                        $this->activatePremium(
                            $transaction
                        );
                        break;

                    default:
                        Log::warning(
                            'Unknown IntaSend payment state.',
                            [
                                'state' => $state,
                                'reference' =>
                                    $transaction->reference,
                            ]
                        );

                        return response()->json([
                            'message' =>
                                'Unsupported payment state.',
                        ], 422);
                }

                $transaction->save();

                return response()->json([
                    'message' => 'Webhook processed successfully.',
                    'reference' => $transaction->reference,
                    'status' => $transaction->status,
                ]);
            });
        } catch (Throwable $e) {
            report($e);

            Log::error(
                'IntaSend webhook processing failed.',
                [
                    'api_ref' => $apiReference,
                    'state' => $state,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'message' => 'Webhook processing failed.',
            ], 500);
        }
    }

    private function validatePaymentDetails(
        PaymentTransaction $transaction,
        array $payload
    ): void {
        $currency = strtoupper(
            (string) ($payload['currency'] ?? '')
        );

        if ($currency !== strtoupper($transaction->currency)) {
            throw new \RuntimeException(
                'Payment currency does not match transaction currency.'
            );
        }

        $value = $this->toFloat(
            $payload['value'] ?? null
        );

        $expectedAmount = (float) $transaction->amount;

        if (abs($value - $expectedAmount) > 0.01) {
            throw new \RuntimeException(
                'Payment amount does not match transaction amount.'
            );
        }
    }

    private function activatePremium(
        PaymentTransaction $transaction
    ): void {
        $subscription = Subscription::query()
            ->where('user_id', $transaction->user_id)
            ->lockForUpdate()
            ->first();

        if (!$subscription) {
            $subscription = Subscription::create([
                'user_id' => $transaction->user_id,
                'plan' => 'basic',
                'status' => 'active',
            ]);
        }

        $durationDays = max(
            1,
            (int) config(
                'services.intasend.premium_duration_days',
                30
            )
        );

        $now = now();

        $baseDate = (
            $subscription->expires_at !== null &&
            $subscription->expires_at->isFuture()
        )
            ? $subscription->expires_at->copy()
            : $now->copy();

        $subscription->update([
            'plan' => 'premium',
            'status' => 'active',
            'starts_at' => $subscription->starts_at !== null &&
                    $subscription->starts_at->isFuture()
                ? $subscription->starts_at
                : $now,
            'expires_at' => $baseDate->addDays(
                $durationDays
            ),
            'provider' => 'intasend',
        ]);
    }

    private function toFloat(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function safePayload(array $payload): array
    {
        return [
            'api_ref' => $payload['api_ref'] ?? null,
            'invoice_id' => $payload['invoice_id'] ?? null,
            'state' => $payload['state'] ?? null,
            'provider' => $payload['provider'] ?? null,
            'currency' => $payload['currency'] ?? null,
        ];
    }
}