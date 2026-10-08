<?php

return [
    'enabled' => (bool) env('FCM_ENABLED', false),
    'project_id' => env('FCM_PROJECT_ID', 'octogear-1d72b'),
    // An absolute path outside the repository; never ship server keys in Flutter.
    'credentials' => env('FCM_CREDENTIALS'),
    'connection' => env('FCM_QUEUE_CONNECTION', 'database'),
    'queue' => 'push',
    'stale_days' => 60,
];
