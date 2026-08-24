<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auth Messages
    |--------------------------------------------------------------------------
    */

    'otp' => [
        'sent'              => 'OTP sent successfully.',
        'verified'          => 'OTP verified. Please complete your profile.',
        'invalid'           => 'Invalid or expired OTP.',
        'rate_limited'      => 'Invalid or expired OTP. Maximum :max attempts allowed.',
        'expires_in'        => 'OTP expires in :minutes minutes.',
    ],

    'login' => [
        'success'           => 'Login successful.',
        'invalid'           => 'Invalid credentials.',
        'blocked'           => 'Your account has been blocked.',
        'rate_limited'      => 'Too many login attempts. Please try again in :minutes minutes.',
    ],

    'register' => [
        'completed'         => 'Registration completed successfully.',
        'token_invalid'     => 'Invalid or expired registration token.',
        'token_required'    => 'Registration token is required.',
    ],

    'admin' => [
        'blocked'           => 'Your admin account has been blocked.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Messages
    |--------------------------------------------------------------------------
    */

    'validation' => [
        'mobile' => [
            'required'      => 'Mobile number is required.',
            'regex'         => 'Mobile must be a valid Saudi number (05XXXXXXXX).',
        ],
        'otp' => [
            'required'      => 'OTP code is required.',
            'digits'        => 'OTP must be exactly 4 digits.',
        ],
        'type' => [
            'enum'          => 'Type must be either "customer" or "service_provider".',
        ],
        'temp_token' => [
            'required'      => 'Registration token is required.',
            'max'           => 'Invalid registration token.',
        ],
        'full_name' => [
            'required'      => 'Full name is required.',
            'max'           => 'Full name must not exceed 100 characters.',
        ],
        'city_id' => [
            'required'      => 'City is required.',
            'integer'       => 'City must be a valid ID.',
            'exists'        => 'Selected city does not exist.',
        ],
        'email' => [
            'required'      => 'Email is required.',
            'email'         => 'Must be a valid email address.',
        ],
        'password' => [
            'required'      => 'Password is required.',
            'min'           => 'Password must be at least :min characters.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Messages
    |--------------------------------------------------------------------------
    */

    'middleware' => [
        'user_blocked'      => 'Your account has been blocked.',
        'admin_blocked'     => 'Your admin account has been blocked.',
        'customer_only'     => 'This endpoint is for customers only.',
        'provider_only'     => 'This endpoint is for service providers only.',
    ],

    /*
    |--------------------------------------------------------------------------
    | General Messages
    |--------------------------------------------------------------------------
    */

    'general' => [
        'ok'                => 'OK',
        'unauthenticated'   => 'Unauthenticated.',
        'validation_failed' => 'Validation failed',
        'not_found'         => 'Not found',
        'unauthorized'      => 'Unauthorized',
        'forbidden'         => 'Forbidden',
    ],

];
