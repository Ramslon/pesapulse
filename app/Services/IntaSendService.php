<?php

namespace App\Services;

use IntaSend\IntaSendPHP\Checkout;
use IntaSend\IntaSendPHP\Customer;
use RuntimeException;

class IntaSendService
{
    /**
     * Create an IntaSend checkout URL.
     *
     * The publishable key is sufficient for Checkout URL creation.
     */
    public function createCheckout(
        float $amount,
        string $currency,
        string $apiReference,
        string $firstName,
        string $lastName,
        string $email,
        ?string $phoneNumber = null,
    ): object {
        $publishableKey = config('services.intasend.publishable_key');

        if (!$publishableKey) {
            throw new RuntimeException(
                'IntaSend publishable key is not configured.'
            );
        }

        $customer = new Customer();

        $customer->first_name = $firstName;
        $customer->last_name = $lastName;
        $customer->email = $email;
        $customer->country = 'KE';

        if ($phoneNumber) {
            $customer->phone_number = $phoneNumber;
        }

        $checkout = new Checkout();

        $checkout->init([
            'publishable_key' => $publishableKey,
            'test' => (bool) config(
                'services.intasend.test',
                true
            ),
        ]);

        $host = config(
            'app.url',
            'http://127.0.0.1:8000'
        );

        $redirectUrl = rtrim($host, '/')
            . '/api/subscription/payment-return';

        return $checkout->create(
            amount: $amount,
            currency: $currency,
            customer: $customer,
            host: $host,
            redirect_url: $redirectUrl,
            api_ref: $apiReference,
            comment: 'PesaPulse Premium subscription',
            method: null,
        );
    }
}