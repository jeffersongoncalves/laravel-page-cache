## Laravel Page Cache

### Overview
Laravel Page Cache provides a full-page response cache middleware for stateless public pages. It caches `200` `GET` responses from guest visitors, keyed by a version token, locale, and theme cookie, and exposes an `X-Page-Cache` HIT/MISS header. A static `flush()` helper bumps the version token to invalidate every cached page at once.

**Namespace:** `JeffersonGoncalves\PageCache`
**Service Provider:** `PageCacheServiceProvider` (auto-discovered)
**Middleware:** `JeffersonGoncalves\PageCache\Middleware\CachePublicPage`

### Key Concepts
- **Middleware:** Register `CachePublicPage` as the outermost middleware on your public route group.
- **Guest only:** Authenticated requests are never cached, so personalised responses are never shared.
- **200 GET only:** Only stateless `GET` requests that return a `200` response are stored.
- **Version token:** `CachePublicPage::flush()` bumps an internal version token to invalidate every cached page.
- **HIT/MISS header:** A computed-and-stored response carries `X-Page-Cache: MISS`; a cache-served response carries `X-Page-Cache: HIT`.

### Middleware Usage

@verbatim
<code-snippet name="middleware-usage" lang="php">
use Illuminate\Support\Facades\Route;
use JeffersonGoncalves\PageCache\Middleware\CachePublicPage;

Route::middleware(CachePublicPage::class)->group(function () {
    Route::get('/', HomeController::class);
    Route::get('/{slug}', ShowController::class);
});
</code-snippet>
@endverbatim

### Invalidating the Cache

@verbatim
<code-snippet name="flush" lang="php">
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
</code-snippet>
@endverbatim

### Configuration
- Publish with `php artisan vendor:publish --tag="laravel-page-cache-config"`.
- `enabled` (default `true`): when `false`, the middleware is a no-op.
- `ttl` (default `3600`): how long, in seconds, a cached page lives.
- `key.locale` (default `true`): fold the current locale into the cache key.
- `key.theme.enabled` (default `true`): fold the theme cookie value into the cache key.
- `key.theme.cookie` (default `theme`): the cookie name read for the theme segment.

### Conventions
- The middleware lives in the `JeffersonGoncalves\PageCache\Middleware\` namespace.
- The cache key uses the request PATH (not the full URL), so the query string cannot flood the cache.
- The theme segment resolves to `light` only when the cookie equals `light`; otherwise `dark`.
- `flush()` is static and only bumps the version token -- it never iterates individual cache keys.
