<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway
    |--------------------------------------------------------------------------
    |
    | driver:
    |   - "stub"  : Simulates a successful credit-card charge (default, no keys needed).
    |   - "moyasar" / "tap" / "paytabs" / "hyperpay" : real gateway (TODO).
    |
    | Set the real driver and put its secrets in your .env file. Never hardcode
    | keys here — always read them from the environment.
    |
    */

    'driver' => env('PAYMENT_DRIVER', 'stub'),

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
