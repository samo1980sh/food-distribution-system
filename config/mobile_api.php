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

    'default_page_size' => (int) env('MOBILE_API_DEFAULT_PAGE_SIZE', 25),
    'max_page_size' => (int) env('MOBILE_API_MAX_PAGE_SIZE', 100),

    'expense_receipt_max_kb' => (int) env(
        'MOBILE_API_EXPENSE_RECEIPT_MAX_KB',
        5120,
    ),
];
