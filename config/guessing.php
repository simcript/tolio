<?php

return [
    // Absolute SQLite path; empty = src/Modules/Guessing/Files/kalaqpar.sqlite
    'database' => env('KALAQPAR_DATABASE', ''),
    'default_rounds' => env('KALAQPAR_DEFAULT_ROUNDS', 5),
    'max_rounds' => env('KALAQPAR_MAX_ROUNDS', 9),
    'reveal_seconds' => env('KALAQPAR_REVEAL_SECONDS', 12),
    'round_max_seconds' => env('KALAQPAR_ROUND_MAX_SECONDS', 180),
    'finish_grace_seconds' => env('KALAQPAR_FINISH_GRACE', 8),
    'lobby_timeout_seconds' => env('KALAQPAR_LOBBY_TIMEOUT', 1800),
    'guess_cooldown' => env('KALAQPAR_GUESS_COOLDOWN', 2),
    'wrong_guess_limit' => env('KALAQPAR_WRONG_GUESS_LIMIT', 10),
    'guest_ttl_days' => env('KALAQPAR_GUEST_TTL_DAYS', 365),
    'retention_days' => env('KALAQPAR_RETENTION_DAYS', 14),
    'heartbeat_timeout' => env('KALAQPAR_HEARTBEAT_TIMEOUT', 90),
    'worker_interval' => env('KALAQPAR_WORKER_INTERVAL', 1),
    // Bounded worker runs (scripts/CI); 0 = run until Ctrl+C
    'worker_ticks' => env('KALAQPAR_WORKER_TICKS', 0),
];
