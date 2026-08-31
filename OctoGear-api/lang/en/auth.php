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
        'car_name_id' => [
            'required'      => 'Car name is required.',
            'integer'       => 'Car name must be a valid ID.',
            'exists'        => 'Selected car name does not exist.',
        ],
        'manufacturing_year' => [
            'required'      => 'Manufacturing year is required.',
            'integer'       => 'Manufacturing year must be a valid number.',
            'min'           => 'Manufacturing year must be at least :min.',
            'max'           => 'Manufacturing year must not exceed :max.',
        ],
        'vehicle_plat_number' => [
            'required'      => 'Plate number is required.',
            'max'           => 'Plate number must not exceed 50 characters.',
        ],
        'color_id' => [
            'required'      => 'Color is required.',
            'integer'       => 'Color must be a valid ID.',
            'exists'        => 'Selected color does not exist.',
        ],
        'fuel_type' => [
            'required'      => 'Fuel type is required.',
            'integer'       => 'Fuel type must be a valid ID.',
            'exists'        => 'Selected fuel type does not exist.',
        ],
        'pictures' => [
            'array'         => 'Pictures must be an array.',
            'max'           => 'Maximum :max pictures allowed.',
        ],
        'order_type' => [
            'required'      => 'Order type is required.',
            'in'            => 'Order type must be "general" or "specific".',
        ],
        'quantity' => [
            'required'      => 'Quantity is required.',
            'integer'       => 'Quantity must be a valid number.',
            'min'           => 'Quantity must be at least :min.',
        ],
        'customer_image' => [
            'string'        => 'Customer image must be a string.',
            'max'           => 'Customer image must not exceed 255 characters.',
        ],
        'store_car_component_id' => [
            'required'      => 'Car component is required for specific orders.',
            'exists'        => 'Selected car component does not exist.',
            'integer'       => 'Car component must be a valid ID.',
        ],
        'model_id' => [
            'required'      => 'Car model is required for general orders.',
            'exists'        => 'Selected car model does not exist.',
            'integer'       => 'Car model must be a valid ID.',
        ],
        'offer_id' => [
            'required'      => 'Offer ID is required.',
            'integer'       => 'Offer ID must be a valid number.',
            'exists'        => 'Offer not found.',
        ],
        'order' => [
            'not_found'     => 'Order not found.',
            'not_mine'      => 'This order does not belong to you.',
            'cannot_cancel' => 'This order cannot be cancelled.',
            'cannot_accept_offer' => 'Cannot accept offer for this order.',
            'offer_not_for_order' => 'This offer does not belong to this order.',
            'cannot_complete' => 'This order cannot be marked as received.',
        ],
        'payment' => [
            'cannot_pay'    => 'This order cannot be paid right now.',
            'already_paid'  => 'This order has already been paid.',
            'gateway_error' => 'Payment could not be completed. Please try again.',
        ],
        'store_car_component' => [
            'not_found'        => 'Selected car component does not exist.',
            'out_of_stock'     => 'This component is currently out of stock.',
            'insufficient_stock' => 'Only :stock unit(s) available for this component.',
        ],
        'rating' => [
            'order_not_found'      => 'Order not found.',
            'order_not_completed'  => 'You can only rate a completed order.',
            'store_mismatch'       => 'The store does not match this order.',
            'already_rated'        => 'This order has already been rated.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Messages
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        'new_order'       => 'New order #:id has been placed.',
        'new_offer'       => 'A new offer has been submitted on your order.',
        'order_paid'      => 'Your order #:id has been paid.',
        'order_completed' => 'Order #:id has been confirmed as received.',
        'new_message'     => 'You have a new message.',
        'all_read'        => 'All notifications marked as read.',
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
