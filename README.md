<div class="filament-hidden">

![Laravel Page Cache](https://raw.githubusercontent.com/jeffersongoncalves/laravel-page-cache/master/art/jeffersongoncalves-laravel-page-cache.png)

</div>

# Laravel Page Cache

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jeffersongoncalves/laravel-page-cache.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-page-cache)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/laravel-page-cache/run-tests.yml?branch=master&label=tests&style=flat-square)](https://github.com/jeffersongoncalves/laravel-page-cache/actions?query=workflow%3Arun-tests+branch%3Amaster)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/laravel-page-cache/fix-php-code-style-issues.yml?branch=master&label=code%20style&style=flat-square)](https://github.com/jeffersongoncalves/laravel-page-cache/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/jeffersongoncalves/laravel-page-cache.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-page-cache)

This Laravel package provides a full-page response cache middleware for stateless public GET pages. It caches 200 responses keyed by a version token, locale, and theme cookie, skips authenticated requests, exposes an `X-Page-Cache` HIT/MISS header, and offers a static `flush()` helper to invalidate every cached page at once.

## Installation

You can install the package via composer:

```bash
composer require jeffersongoncalves/laravel-page-cache
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-page-cache-config"
```

This is the contents of the published config file:

```php
return [
    'enabled' => env('PAGE_CACHE_ENABLED', true),

    // A TTL of 0 (or below) means "cache forever".
    'ttl' => (int) env('PAGE_CACHE_TTL', 3600),

    // Fold a normalized query string into the cache key (safe default).
    'include_query_string' => env('PAGE_CACHE_INCLUDE_QUERY_STRING', true),

    'key' => [
        'locale' => true,

        // Vary the cache on the negotiated content encoding.
        'accept_encoding' => true,

        'theme' => [
            'enabled' => true,
            'cookie' => 'theme',
        ],
    ],
];
```

## Usage

Register `CachePublicPage` **after** `StartSession` and the authentication middleware — i.e. inside your `web` middleware group, not as the outermost middleware. The cache deliberately depends on a started session to tell guests from authenticated users, and registering it before `StartSession` would make it bail out on every request:

```php
use Illuminate\Support\Facades\Route;
use JeffersonGoncalves\PageCache\Middleware\CachePublicPage;

Route::middleware(['web', CachePublicPage::class])->group(function () {
    Route::get('/', HomeController::class);
    Route::get('/{slug}', ShowController::class);
});
```

The first request to a path is computed normally and stored with an `X-Page-Cache: MISS` header. Subsequent requests are served straight from the cache with an `X-Page-Cache: HIT` header. Only stateless `GET` requests that return a `200` response from a guest (unauthenticated) visitor are cached, and the full response header bag (minus `Set-Cookie`) is replayed on a cache hit.

### What is never cached (important)

To avoid leaking per-visitor state across visitors, the middleware **passes the request through untouched** (no read, no write) when any of the following is true:

- the request is authenticated (`$request->user()` is not `null`);
- there is no started session (so register it **after** `StartSession`);
- the response sets its own cookies (`Set-Cookie`);
- the response sends `Cache-Control: no-store`.

`Cache-Control: no-store` is the explicit opt-out you should reach for whenever a page renders per-visitor state. (Only `no-store` is honoured: Symfony stamps the default `Cache-Control: no-cache, private` on every response that does not set its own cache headers, so `no-cache`/`private` cannot be used as opt-out signals without disabling the cache for every page.)

> **Session/CSRF limitation — read this.** Laravel flushes queued cookies (the session cookie, `XSRF-TOKEN`, flash data) into the response **after** this middleware has already inspected it, so the `Set-Cookie` guard above cannot see them. A page that embeds a `@csrf` token in a `<form>` therefore looks cacheable, and caching it would replay one visitor's CSRF token to everyone else. **Any response that contains a CSRF token, a form, flash messages, or other guest-session content must mark itself with `Cache-Control: no-store`** (e.g. `return response($html)->header('Cache-Control', 'no-store');`) or be kept out of the cached route group entirely. There is no reliable way for the middleware to detect this for you.

### Invalidating the cache

Call `CachePublicPage::flush()` from your model observers to invalidate every cached page whenever the underlying content changes:

```php
use JeffersonGoncalves\PageCache\Middleware\CachePublicPage;

class ProjectObserver
{
    public function saved(Project $project): void
    {
        CachePublicPage::flush();
    }

    public function deleted(Project $project): void
    {
        CachePublicPage::flush();
    }
}
```

`flush()` bumps an internal version token, so every previously cached page is bypassed on the next request without touching individual cache keys.

### Cache key

By default the cache key is composed of the version token, the current locale, the negotiated `Accept-Encoding`, the theme cookie value, a hash of the request path, and a hash of a normalized (sorted) query string. You can disable the locale, `accept_encoding`, or theme segments — or change the theme cookie name — through the config file. The `Accept-Encoding` segment is normalized (tokens lowercased and sorted) so a body compressed for a gzip/br client is never replayed to a client that cannot decode it.

The path (not the full URL) is used for the path segment, and the query string is normalized and hashed separately so that `?b=2&a=1` and `?a=1&b=2` collapse to the same entry while `/products?page=2` stays distinct from `/products?page=1`. If a route ignores the query string entirely you can drop it from the key by setting `include_query_string` to `false`.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Jèfferson Gonçalves](https://github.com/jeffersongoncalves)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
