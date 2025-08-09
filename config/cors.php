<?php

return [
    /*
     * Paths that should have CORS headers applied
     */
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'docs/api*'],

    /*
     * Allowed HTTP methods
     */
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    /*
     * Allowed origins for API access
     */
    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:3001',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:3001',
        env('FRONTEND_URL', 'http://localhost:3000'),
    ],

    /*
     * Allowed origin patterns
     */
    'allowed_origins_patterns' => [
        // Allow any localhost port for development
        '/^http:\/\/localhost:\d+$/',
        '/^http:\/\/127\.0\.0\.1:\d+$/',
    ],

    /*
     * Allowed headers
     */
    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-API-Version',
        'X-CSRF-TOKEN',
        'Origin',
        'User-Agent',
        'Cache-Control',
    ],

    /*
     * Headers to expose to the browser
     */
    'exposed_headers' => [
        'X-API-Version',
        'X-API-Supported-Versions',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
    ],

    /*
     * Max age for preflight requests
     */
    'max_age' => 86400, // 24 hours

    /*
     * Whether to support credentials (cookies, authorization headers)
     */
    'supports_credentials' => true,
];
