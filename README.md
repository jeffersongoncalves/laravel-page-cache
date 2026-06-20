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

    'ttl' => (int) env('PAGE_CACHE_TTL', 3600),

    'key' => [
        'locale' => true,

        'theme' => [
            'enabled' => true,
            'cookie' => 'theme',
        ],
    ],
];
```

## Usage

Register `CachePublicPage` as the outermost middleware on your public route group so cached responses are served before any other middleware runs:

```php
use Illuminate\Support\Facades\Route;
use JeffersonGoncalves\PageCache\Middleware\CachePublicPage;

Route::middleware(CachePublicPage::class)->group(function () {
    Route::get('/', HomeController::class);
    Route::get('/{slug}', ShowController::class);
});
```

The first request to a path is computed normally and stored with an `X-Page-Cache: MISS` header. Subsequent requests are served straight from the cache with an `X-Page-Cache: HIT` header. Only stateless `GET` requests that return a `200` response from a guest (unauthenticated) visitor are cached.

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

By default the cache key is composed of the version token, the current locale, the theme cookie value, and a hash of the request path. You can disable the locale or theme segments — or change the theme cookie name — through the config file. The path (not the full URL) is used so the query string cannot flood the cache with variants.

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
