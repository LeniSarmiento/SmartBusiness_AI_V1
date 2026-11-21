<?php

return [
    'http' => [
        'enabled' => env('OBSERVABILITY_HTTP_LOGGING', true),
        'excluded_paths' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('OBSERVABILITY_HTTP_EXCLUDED_PATHS', 'up'))
        ))),
    ],

    'database' => [
        'slow_query_logging' => env('DB_SLOW_QUERY_LOGGING', true),
        'slow_query_ms' => (float) env('DB_SLOW_QUERY_MS', 250),
    ],

    'queue' => [
        'log_processed_jobs' => env('QUEUE_LOG_PROCESSED_JOBS', true),
    ],
];
