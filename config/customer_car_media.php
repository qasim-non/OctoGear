<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Customer-car media storage
    |--------------------------------------------------------------------------
    |
    | New uploads use this private disk. Each picture records the disk used at
    | upload time, so moving future uploads to S3 does not break existing local
    | files while a migration is in progress.
    |
    */

    'disk' => env('CUSTOMER_CAR_MEDIA_DISK', 'customer_car_media_local'),

    'max_files' => 5,

    'max_file_size_kb' => 5 * 1024,

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
    'min_width' => 1,
    'min_height' => 1,
    'max_width' => 4096,
    'max_height' => 4096,
];
