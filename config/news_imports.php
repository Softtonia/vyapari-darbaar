<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PIB (Press Information Bureau) RSS Auto-Import
    |--------------------------------------------------------------------------
    |
    | Configuration for automatically importing press releases from the
    | official PIB RSS feed into the News CMS as DRAFT articles.
    |
    | Prerequisites (must be pre-configured by Admin):
    |   - A NewsSource with code matching 'source_code' must exist and be active.
    |   - A NewsCategory with slug matching 'default_category_slug' must exist
    |     and be active (if specified).
    |
    | Articles are imported as DRAFT — Admin must review and publish manually.
    |
    */
    'pib' => [

        /**
         * Toggle the PIB importer on or off without code changes.
         */
        'enabled' => env('PIB_RSS_ENABLED', true),

        /**
         * Official PIB RSS feed URL.
         * ModId=6 = All Ministries, Lang=1 = Hindi/English, Regid=3 = National
         */
        'url' => env(
            'PIB_RSS_URL',
            'https://pib.gov.in/RssMain.aspx?ModId=6&Lang=1&Regid=3'
        ),

        /**
         * Code of the existing NewsSource record for PIB.
         * Resolved by code; never auto-created at runtime.
         */
        'source_code' => env('PIB_RSS_SOURCE_CODE', 'PIB'),

        /**
         * Category mapping and filtering rules for classification.
         * The importer evaluates each item and maps it to the matching category slug.
         */
        'categories' => [
            'trader' => [
                'category_slug' => env('PIB_RSS_TRADER_CATEGORY_SLUG', 'business'),
                'keywords' => [
                    'trade', 'traders', 'commerce', 'wholesale trade', 'retail trade',
                    'exports', 'imports', 'commodities trade', 'markets', 'msme', 'business',
                    'merchant', 'supply chain', 'trade policy', 'export', 'commodity'
                ],
            ],
            'agriculture' => [
                'category_slug' => env('PIB_RSS_AGRICULTURE_CATEGORY_SLUG', 'agriculture'),
                'keywords' => [
                    'agriculture', 'farmers', 'farming', 'crops', 'agricultural',
                    'horticulture', 'food grains', 'pulses', 'oilseeds', 'cereals',
                    'msp', 'procurement', 'mandi', 'fertilizers', 'seeds', 'irrigation',
                    'farmer', 'crop'
                ],
            ],
        ],

        /**
         * Default author name when RSS item has no author field.
         */
        'default_author' => env('PIB_RSS_DEFAULT_AUTHOR', 'Press Information Bureau'),

        /**
         * HTTP request timeout in seconds.
         */
        'timeout_seconds' => (int) env('PIB_RSS_TIMEOUT', 30),

        /**
         * Maximum number of RSS items to process per import run.
         */
        'max_items' => (int) env('PIB_RSS_MAX_ITEMS', 50),

        /**
         * Redis lock key for preventing concurrent import runs.
         */
        'lock_key' => 'news:lock:import:pib',

        /**
         * Redis lock TTL in seconds. Must exceed job timeout (300s).
         */
        'lock_ttl' => (int) env('PIB_RSS_LOCK_TTL', 600),

        /**
         * Dedicated queue for import jobs.
         */
        'queue' => env('PIB_RSS_QUEUE', 'news-import'),

    ],

    /*
    |--------------------------------------------------------------------------
    | SEBI RSS Auto-Import
    |--------------------------------------------------------------------------
    */
    'sebi' => [
        'enabled' => env('SEBI_RSS_ENABLED', true),
        'url' => env('SEBI_RSS_URL', 'https://www.sebi.gov.in/sebirss.xml'),
        'source_code' => env('SEBI_RSS_SOURCE_CODE', 'SEBI'),
        'default_category_slug' => env('SEBI_RSS_CATEGORY_SLUG', 'business'),
        'default_author' => env('SEBI_RSS_DEFAULT_AUTHOR', 'SEBI'),
        'timeout_seconds' => (int) env('SEBI_RSS_TIMEOUT', 30),
        'max_items' => (int) env('SEBI_RSS_MAX_ITEMS', 50),
        'lock_key' => 'news:lock:import:sebi',
        'lock_ttl' => (int) env('SEBI_RSS_LOCK_TTL', 600),
        'queue' => env('SEBI_RSS_QUEUE', 'news-import'),
    ],

];
