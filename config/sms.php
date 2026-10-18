<?php

use App\Integrations\Sms\LogSmsSender;

return [
    // The default preserves development behavior and does not deliver SMS.
    'driver' => env('SMS_DRIVER', 'log'),

    // Add provider adapters here; credentials belong in env-backed config.
    'drivers' => [
        'log' => LogSmsSender::class,
    ],
];
