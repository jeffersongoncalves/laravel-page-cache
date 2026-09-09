# Changelog

All notable changes to `laravel-page-cache` will be documented in this file.

## v1.1.1 - 2026-09-09

Fix: shouldStore() no longer rejects caching on Livewire full-page components. Laravel's routine session-id and XSRF-TOKEN cookies are now allowed through the Set-Cookie check, so page caching works again on any Livewire-driven site. Fixes #5.

## v1.1.0 - 2026-06-21

**Full Changelog**: https://github.com/jeffersongoncalves/laravel-page-cache/compare/v1.0.1...v1.1.0

## v1.0.1 - 2026-06-20

chore: ignore the .phpunit.cache directory.

## v1.0.0 - 2026-06-20

Initial release.
