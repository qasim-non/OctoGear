<?php

use App\Integrations\Payments\StubPaymentGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway
    |--------------------------------------------------------------------------
    |
    | driver:
    |   - "stub"  : Simulates a successful credit-card charge (default, no keys needed).
    | Real gateways require an adapter and lifecycle work; see INTEGRATIONS.md.
    |
    | Set the real driver and put its secrets in your .env file. Never hardcode
    | keys here — always read them from the environment.
    |
    */

    'driver' => env('PAYMENT_DRIVER', 'stub'),

    'drivers' => [
        'stub' => StubPaymentGateway::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform Commission
    |--------------------------------------------------------------------------
    |
    | Percentage of each sale the platform keeps. The provider receives
    | (100% - commission_rate) of the gross order amount.
    |
    */
    'commission_rate' => (float) env('PAYMENT_COMMISSION_RATE', 5),

    /*
    |--------------------------------------------------------------------------
    | Gateway Secrets (placeholder — populate via .env)
    |--------------------------------------------------------------------------
    */
    'moyasar' => [
        'secret_key' => env('MOYASAR_SECRET_KEY'),
    ],
    'tap' => [
        'secret_key' => env('TAP_SECRET_KEY'),
    ],
];
