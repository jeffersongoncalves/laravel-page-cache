<?php

// config for JeffersonGoncalves/PageCache

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | When disabled, the middleware becomes a no-op: every request is passed
    | straight through without reading from or writing to the cache.
    |
    */

    'enabled' => env('PAGE_CACHE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Time To Live
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) a cached page is kept before it is regenerated.
    |
    */

    'ttl' => (int) env('PAGE_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Cache Key
    |--------------------------------------------------------------------------
    |
    | Controls which request attributes are folded into the cache key on top
    | of the version token and the request path. The defaults reproduce the
    | original behaviour: one entry per locale and per theme cookie value.
    |
    */

    'key' => [

        // Include the current application locale in the cache key.
        'locale' => true,

        'theme' => [
            // Include the theme cookie value in the cache key.
            'enabled' => true,

            // The cookie name read to determine the visitor's theme.
            'cookie' => 'theme',
        ],

    ],

];
