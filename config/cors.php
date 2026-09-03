<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Environment-driven CORS configuration for Vyapari Darbaar API.
    | Restricts allowed origins, methods, and headers for production readiness.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter(array_map('trim', explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        env('FRONTEND_ADMIN_URL', 'http://localhost:3000').','.env('FRONTEND_USER_URL', 'http://localhost:3000')
    )))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With', 'Origin', 'User-Agent'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
