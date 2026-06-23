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
    | A value of 0 (or below) means "cache forever" — the entry is stored with
    | Cache::forever() and only cleared by flush() or your cache driver.
    |
    */

    'ttl' => (int) env('PAGE_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Include Query String
    |--------------------------------------------------------------------------
    |
    | When enabled (the safe default), a normalized (sorted) query string is
    | folded into the cache key so /products?page=2 is stored separately from
    | /products?page=1. Disable this only for routes you know ignore the query
    | string entirely.
    |
    */

    'include_query_string' => env('PAGE_CACHE_INCLUDE_QUERY_STRING', true),

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

        // Vary the cache on the request's negotiated content encoding so a
        // compressed body is never replayed to a client that cannot decode it.
        'accept_encoding' => true,

        'theme' => [
            // Include the theme cookie value in the cache key.
            'enabled' => true,

            // The cookie name read to determine the visitor's theme.
            'cookie' => 'theme',
        ],

    ],

];
