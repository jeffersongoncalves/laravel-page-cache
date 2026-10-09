# Changelog

All notable changes to `laravel-page-cache` will be documented in this file.

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
