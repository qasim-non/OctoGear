<?php

return [
    // Also requires APP_ENV=local. Never enable on a shared/public server.
    'expose_for_testing' => env('OTP_EXPOSE_FOR_TESTING', false),
];
