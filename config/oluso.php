<?php

declare(strict_types=1);

return [
    'api_key' => env('OLUSO_API_KEY', ''),
    'endpoint' => env('OLUSO_ENDPOINT', \Oluso\Options::DEFAULT_ENDPOINT),
    'environment' => env('OLUSO_ENVIRONMENT', env('APP_ENV', 'production')),
    'max_breadcrumbs' => (int) env('OLUSO_MAX_BREADCRUMBS', 30),
    'max_errors_per_minute' => (int) env('OLUSO_MAX_ERRORS_PER_MINUTE', 60),
    'sensitive_keys' => [],

    // Laravel has a natural post-response hook (terminable middleware), so
    // reports are queued during the request and sent afterward by default,
    // rather than adding request latency by sending synchronously.
    'defer_send' => true,
];
