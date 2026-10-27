<?php

return [
    'access_minutes' => (int) env('AUTH_ACCESS_MINUTES', 10),
    'session_days' => (int) env('AUTH_SESSION_DAYS', 14),
    'refresh_retry_seconds' => 60,
    'browser_origins' => array_values(array_filter(array_map('trim', explode(',', env('AUTH_BROWSER_ORIGINS', 'http://localhost:4200,http://127.0.0.1:4200'))))),
    'cookie_name' => 'octogear_admin_refresh',
    'cookie_secure' => env('AUTH_COOKIE_SECURE', true),
    'cookie_same_site' => env('AUTH_COOKIE_SAME_SITE', 'none'),
];
