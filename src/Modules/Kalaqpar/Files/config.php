<?php
return [
    // Absolute application root, including a deployment prefix; never HTTP_HOST.
    'public_base_url' => env('KQ_PUBLIC_BASE_URL', env('APP_URL', '')),
    // Shared hosting uses short authenticated POST requests; worker mode is optional.
    'progression' => env('KQ_PROGRESSION', 'shared'),
    'maintenance' => env('KQ_MAINTENANCE', false),
    'shared_tick_ms' => 200,
    'database' => env('KQ_DATABASE', dirname(__DIR__) . '/Files/runtime/game.sqlite'),
    'secure_cookie' => env('KQ_SECURE_COOKIE', env('APP_ENV', 'local') === 'production'),
    'rounds' => 15,
    'capacity' => 30,
    'countdown_ms' => 3000,
    'window_ms' => 2500,
    'window_step_ms' => 200,
    'window_floor_ms' => 1500,
    'feedback_ms' => 2000,
    'formation_ms' => 12000,
    'tick_ms' => 100,
    'offline_ms' => 15000,
    'host_transfer_ms' => 30000,
    'outage_ms' => 10000,
    'health_ms' => 2000,
    'room_ttl_ms' => 1800000,
    'identity_days' => 180,
];
