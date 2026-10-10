<?php

return [
    'enabled' => (bool) env('CHAT_REALTIME_ENABLED', false),
    // Public device-reachable base URL; it can differ from Laravel's Reverb host.
    'url' => env('CHAT_WEBSOCKET_URL', 'ws://127.0.0.1:8080'),
    'queue_connection' => env('CHAT_REALTIME_QUEUE_CONNECTION', 'database'),
    'queue' => 'realtime',
];
