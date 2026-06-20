---
name: page-cache-development
description: Development guide for laravel-page-cache, a package that provides a full-page response cache middleware for stateless public GET pages, keyed by version token + locale + theme cookie, with a static flush() helper for mass invalidation.
---

# Page Cache Development Skill

## When to use this skill

- When developing or extending the laravel-page-cache package
- When changing how the cache key is composed (locale, theme, path)
- When adjusting which requests are eligible for caching
- When writing tests for the full-page cache middleware
- When debugging HIT/MISS behaviour or cache invalidation

## Setup

### Requirements
- PHP 8.2+
- Laravel 11, 12, or 13
- `spatie/laravel-package-tools` ^1.14

### Installation

```bash
composer require jeffersongoncalves/laravel-page-cache
```

Publish the config file:

```bash
php artisan vendor:publish --tag="laravel-page-cache-config"
```

## Package Structure

```
src/
  PageCacheServiceProvider.php          # Registers the package + config file
  Middleware/
    CachePublicPage.php                 # Full-page response cache middleware
config/
  page-cache.php                        # enabled, ttl, key (locale + theme cookie)
```

## Features

### CachePublicPage Middleware

Register it as the outermost middleware on the public route group:

```php
use Illuminate\Support\Facades\Route;
use JeffersonGoncalves\PageCache\Middleware\CachePublicPage;

Route::middleware(CachePublicPage::class)->group(function () {
    Route::get('/', HomeController::class);
});
```

Behaviour:

- Only stateless `GET` requests from guests are cached (`$request->user() === null`).
- Only `200` responses are stored, with the content type preserved.
- A computed-and-stored response carries `X-Page-Cache: MISS`.
- A cache-served response carries `X-Page-Cache: HIT`.
- When `page-cache.enabled` is `false`, the middleware is a no-op (no header is set).

### Cache Key

The key is built from segments joined with `:`:

```
page : {version} : {locale?} : {theme?} : sha1(path)
```

- `page` and the version token are always present.
- The locale segment is included when `page-cache.key.locale` is `true`.
- The theme segment is included when `page-cache.key.theme.enabled` is `true`; it resolves to `light` only when the configured cookie equals `light`, otherwise `dark`.
- The path (not the full URL) is hashed, so the query string cannot flood the cache.

### Invalidation via flush()

```php
use JeffersonGoncalves\PageCache\Middleware\CachePublicPage;

CachePublicPage::flush();
```

`flush()` bumps the `pages:version` token stored with `Cache::forever`. Because the version is part of every key, all previously cached pages are bypassed on the next request without iterating individual cache entries. Call it from model observers whenever the underlying content changes.

## Configuration

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

## Testing Patterns

### Caching a Response

```php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use JeffersonGoncalves\PageCache\Middleware\CachePublicPage;

it('caches a 200 GET response', function () {
    Route::middleware(CachePublicPage::class)->get('/cached', fn () => (string) Str::uuid());

    $first = $this->get('/cached')->assertHeader('X-Page-Cache', 'MISS');
    $second = $this->get('/cached')->assertHeader('X-Page-Cache', 'HIT');

    expect($second->getContent())->toBe($first->getContent());
});
```

### Skipping Authenticated Requests

```php
use Illuminate\Foundation\Auth\User;

it('does not cache authenticated requests', function () {
    $this->actingAs(new User);

    $this->get('/cached')->assertHeaderMissing('X-Page-Cache');
});
```

### Invalidation

```php
it('invalidates cached pages on flush', function () {
    $this->get('/cached')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/cached')->assertHeader('X-Page-Cache', 'HIT');

    CachePublicPage::flush();

    $this->get('/cached')->assertHeader('X-Page-Cache', 'MISS');
});
```

### Theme-aware Keys

```php
it('stores a separate entry per theme cookie', function () {
    $this->withUnencryptedCookie('theme', 'light')->get('/cached')->assertHeader('X-Page-Cache', 'MISS');
    $this->withUnencryptedCookie('theme', 'dark')->get('/cached')->assertHeader('X-Page-Cache', 'MISS');
    $this->withUnencryptedCookie('theme', 'light')->get('/cached')->assertHeader('X-Page-Cache', 'HIT');
});
```

### Running Tests

```bash
# Run all tests
vendor/bin/pest

# Run with coverage
vendor/bin/pest --coverage

# Static analysis
vendor/bin/phpstan analyse

# Code formatting
vendor/bin/pint
```

## Conventions

- The middleware reads all settings from `config('page-cache.*')`.
- The cache key uses the request PATH, never the full URL.
- The theme segment resolves to `dark` unless the cookie equals `light`.
- `flush()` only bumps the version token -- never iterate cache keys.
- Use the `array` cache store in tests (`config()->set('cache.default', 'array')`).
