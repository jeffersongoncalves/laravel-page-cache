# Changelog

All notable changes to `laravel-page-cache` will be documented in this file.

## v1.4.0 - 2026-10-24

### Added

- `ServeCachedPage` middleware (opt-in), prepended to the `web` group so it runs before `StartSession`. A `GET` with no session cookie, no `remember_*` cookie and no `Authorization` header gets its HIT with no session write and no `Set-Cookie`. A MISS, or any request with a session, falls through to `CachePublicPage` as before. (#14, #15)
- `early.s_maxage` config (`PAGE_CACHE_EARLY_S_MAXAGE`, default 0): when above 0, early HITs get `Cache-Control: public, s-maxage=N` so a CDN can cache them.
- `PageCache::key(Request)` and `PageCache::cachedResponse(string)`, shared by both middlewares, so a page stored by one is served by the other.

See the README for the caveats: CSRF tokens on early-served pages, locale and theme cookie must be known before the session starts, route middleware is skipped on early HITs, and CDN copies outlive `flush()`/`forget()` within `s-maxage`.

### Maintenance

- CI: the Pint workflow can push its fixes; bump `shivammathur/setup-php` (#13).

## v1.3.0 - 2026-10-09

Cached pages keep the CSP nonce they were rendered with (`Vite::cspNonce()`) and restore it on a HIT, so a CSP header built after this middleware — laravel-security-headers 2.1+ — matches the cached markup instead of blocking its inline scripts. Apps without a CSP nonce are unaffected.

## v1.2.0 - 2026-10-08

Runtime controls, all stored in the application cache (no deploy needed):

- `PageCache::forget('/path')` invalidates every variant of one path (locales, themes, encodings, query strings)
- `PageCache::pause()` / `resume()` turn the cache off and on at runtime
- `PageCache::stats()` returns enabled, paused, TTL, version, hits, misses, hit ratio and last flush; counters can be disabled with `page-cache.stats`
- `CachePublicPage::flush()` keeps working and now also resets the counters

The cache key now carries a per-path version, so existing cached pages are recomputed once after upgrading.

## v1.1.1 - 2026-09-09

Fix: shouldStore() no longer rejects caching on Livewire full-page components. Laravel's routine session-id and XSRF-TOKEN cookies are now allowed through the Set-Cookie check, so page caching works again on any Livewire-driven site. Fixes #5.

## v1.1.0 - 2026-06-21

**Full Changelog**: https://github.com/jeffersongoncalves/laravel-page-cache/compare/v1.0.1...v1.1.0

## v1.0.1 - 2026-06-20

chore: ignore the .phpunit.cache directory.

## v1.0.0 - 2026-06-20

Initial release.
