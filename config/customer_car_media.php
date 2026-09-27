<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Customer-car media storage
    |--------------------------------------------------------------------------
    |
    | Null settings inherit the shared images configuration. Existing disk
    | overrides remain supported, and every picture retains its original disk.
    |
    */

    'disk' => env('CUSTOMER_CAR_MEDIA_DISK'),

    'max_files' => env('CUSTOMER_CAR_MEDIA_MAX_FILES'),

    'max_file_size_kb' => env('CUSTOMER_CAR_MEDIA_MAX_FILE_SIZE_KB'),

    // A key is replayable only for this bounded window. The scheduled purge
    // removes the key and its fingerprint afterwards, while the create flow
    // also enforces expiry in case the scheduler is temporarily unavailable.
    'idempotency_retention_hours' => max(
        1,
        (int) env('CUSTOMER_CAR_IDEMPOTENCY_RETENTION_HOURS', 24),
    ),

    // `dimensions` rejects unexpectedly large images before they reach the
    // application. It does not normalize orientation or remove EXIF data.
    // Before production, provision GD, Imagick, or a managed image service
    // and add server-side normalization/metadata stripping to this workflow.
    'min_width' => env('CUSTOMER_CAR_MEDIA_MIN_WIDTH'),
    'min_height' => env('CUSTOMER_CAR_MEDIA_MIN_HEIGHT'),
    'max_width' => env('CUSTOMER_CAR_MEDIA_MAX_WIDTH'),
    'max_height' => env('CUSTOMER_CAR_MEDIA_MAX_HEIGHT'),
];
