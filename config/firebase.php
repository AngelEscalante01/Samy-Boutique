<?php

return [
    'project_id' => env('FIREBASE_PROJECT_ID'),
    'credentials' => env('FIREBASE_CREDENTIALS'),
    'vapid_key' => env('VITE_FIREBASE_VAPID_KEY'),
    'web' => [
        'api_key' => env('VITE_FIREBASE_API_KEY'),
        'auth_domain' => env('VITE_FIREBASE_AUTH_DOMAIN'),
        'project_id' => env('VITE_FIREBASE_PROJECT_ID'),
        'storage_bucket' => env('VITE_FIREBASE_STORAGE_BUCKET'),
        'messaging_sender_id' => env('VITE_FIREBASE_MESSAGING_SENDER_ID'),
        'app_id' => env('VITE_FIREBASE_APP_ID'),
    ],
    'js_sdk_version' => env('FIREBASE_JS_SDK_VERSION', '12.19.0'),
    'low_stock_threshold' => (int) env('NOTIFICATIONS_LOW_STOCK_THRESHOLD', 3),
    'layaway_expiry_days' => (int) env('LAYAWAY_EXPIRY_DAYS', 30),
    'layaway_scan_enabled' => (bool) env('NOTIFICATIONS_LAYAWAY_SCAN_ENABLED', false),
    'layaway_scan_min_id' => env('NOTIFICATIONS_LAYAWAY_SCAN_MIN_ID'),
    'layaway_timezone' => env('NOTIFICATIONS_LAYAWAY_TIMEZONE', 'America/Mexico_City'),
    'delivery_batch_size' => (int) env('NOTIFICATIONS_DELIVERY_BATCH_SIZE', 50),
    'delivery_timeout' => (int) env('NOTIFICATIONS_DELIVERY_TIMEOUT', 8),
    'push_enabled' => (bool) env('NOTIFICATIONS_PUSH_ENABLED', false),
];
