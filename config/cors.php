<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),
        fn (string $origin) => $origin !== '' && $origin !== '*',
    )),
    'allowed_origins_patterns' => env('CORS_ALLOW_LOCALHOST', in_array(env('APP_ENV', 'production'), ['local', 'testing'], true))
        ? ['#^https?://(?:localhost|127\.0\.0\.1|\[::1\])(?::[0-9]{1,5})?$#']
        : [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['Content-Disposition'],
    'max_age' => 600,
    'supports_credentials' => true,
];
