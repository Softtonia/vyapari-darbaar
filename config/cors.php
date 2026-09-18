<?php

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
    ],

    'allowed_origins' => array_values(array_filter(
        array_map(
            'trim',
            explode(',', (string) env(
                'CORS_ALLOWED_ORIGINS',
                'https://admin.vyaparidarbar.com,https://vyaparidarbar.com'
            ))
        )
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'Origin',
        'User-Agent',
    ],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];