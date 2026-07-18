<?php

return [
    'version' => env('MOBILE_API_VERSION', 'v1'),

    'token_ttl_minutes' => (int) env(
        'MOBILE_API_TOKEN_TTL_MINUTES',
        60 * 24 * 30,
    ),

    'max_sessions' => (int) env('MOBILE_API_MAX_SESSIONS', 5),

    'token_touch_interval_seconds' => (int) env(
        'MOBILE_API_TOKEN_TOUCH_INTERVAL',
        300,
    ),

    'rate_limit_per_minute' => (int) env('MOBILE_API_RATE_LIMIT', 120),

    'login_rate_limit_per_minute' => (int) env(
        'MOBILE_API_LOGIN_RATE_LIMIT',
        5,
    ),

    'token_ability' => 'api:v1',
    'token_name_prefix' => 'mobile:',

    'allowed_roles' => [
        'driver',
        'sales_representative',
    ],

    'default_page_size' => (int) env('MOBILE_API_DEFAULT_PAGE_SIZE', 25),
    'max_page_size' => (int) env('MOBILE_API_MAX_PAGE_SIZE', 100),

    'sync_default_pull_limit' => (int) env(
        'MOBILE_API_SYNC_DEFAULT_PULL_LIMIT',
        200,
    ),

    'sync_max_pull_limit' => (int) env(
        'MOBILE_API_SYNC_MAX_PULL_LIMIT',
        500,
    ),

    'sync_retention_days' => (int) env(
        'MOBILE_API_SYNC_RETENTION_DAYS',
        90,
    ),

    'sync_max_push_operations' => (int) env(
        'MOBILE_API_SYNC_MAX_PUSH_OPERATIONS',
        50,
    ),

    'sync_max_push_operation_kb' => (int) env(
        'MOBILE_API_SYNC_MAX_PUSH_OPERATION_KB',
        256,
    ),

    'sync_push_processing_timeout_seconds' => (int) env(
        'MOBILE_API_SYNC_PUSH_PROCESSING_TIMEOUT_SECONDS',
        300,
    ),

    'expense_receipt_max_kb' => (int) env(
        'MOBILE_API_EXPENSE_RECEIPT_MAX_KB',
        5120,
    ),
];
