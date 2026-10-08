<?php

declare(strict_types=1);

namespace JeffersonGoncalves\PageCache;

use Illuminate\Support\Facades\Cache;

/**
 * Runtime controls for the page cache, all kept in the application cache so they apply without a deploy:
 * flush everything, forget one path, pause/resume, and hit/miss counters since the last flush.
 */
final class PageCache
{
    public const VERSION_KEY = 'pages:version';

    private const PATH_VERSION_KEY = 'pages:path:';

    private const PAUSED_KEY = 'pages:paused';

    private const HITS_KEY = 'pages:hits';

    private const MISSES_KEY = 'pages:misses';

    private const FLUSHED_AT_KEY = 'pages:flushed_at';

    /** Invalidate every cached page and reset the counters. */
    public static function flush(): void
    {
        // Atomic bump: seed the key if missing, then let the store increment it, so two racing flushes can't
        // lose an increment. Entries keyed on the old version become unreachable and expire with their own TTL.
        Cache::add(self::VERSION_KEY, 1);
        Cache::increment(self::VERSION_KEY);

        Cache::forget(self::HITS_KEY);
        Cache::forget(self::MISSES_KEY);
        Cache::forever(self::FLUSHED_AT_KEY, now()->toIso8601String());
    }

    /** Invalidate every cached variant (locale, theme, encoding, query string) of one path. */
    public static function forget(string $path): void
    {
        $key = self::PATH_VERSION_KEY.sha1(self::normalizePath($path));

        Cache::add($key, 0);
        Cache::increment($key);
    }

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    public static function pathVersion(string $path): int
    {
        return (int) Cache::get(self::PATH_VERSION_KEY.sha1(self::normalizePath($path)), 0);
    }

    /** Stop serving and storing cached pages until resume(), without touching the config. */
    public static function pause(): void
    {
        Cache::forever(self::PAUSED_KEY, true);
    }

    public static function resume(): void
    {
        Cache::forget(self::PAUSED_KEY);
    }

    public static function isPaused(): bool
    {
        return (bool) Cache::get(self::PAUSED_KEY, false);
    }

    /** Enabled in the config and not paused at runtime. */
    public static function isActive(): bool
    {
        return (bool) config('page-cache.enabled', true) && ! self::isPaused();
    }

    public static function recordHit(): void
    {
        self::count(self::HITS_KEY);
    }

    public static function recordMiss(): void
    {
        self::count(self::MISSES_KEY);
    }

    /**
     * @return array{enabled: bool, paused: bool, ttl: int, version: int, hits: int, misses: int, hit_ratio: float|null, flushed_at: string|null}
     */
    public static function stats(): array
    {
        $hits = (int) Cache::get(self::HITS_KEY, 0);
        $misses = (int) Cache::get(self::MISSES_KEY, 0);
        $flushedAt = Cache::get(self::FLUSHED_AT_KEY);

        return [
            'enabled' => (bool) config('page-cache.enabled', true),
            'paused' => self::isPaused(),
            'ttl' => (int) config('page-cache.ttl', 3600),
            'version' => self::version(),
            'hits' => $hits,
            'misses' => $misses,
            'hit_ratio' => $hits + $misses > 0 ? round($hits / ($hits + $misses), 4) : null,
            'flushed_at' => is_string($flushedAt) ? $flushedAt : null,
        ];
    }

    /** The same shape Request::path() returns: no surrounding slashes, "/" for the root. */
    public static function normalizePath(string $path): string
    {
        $path = trim((string) parse_url($path, PHP_URL_PATH), '/');

        return $path === '' ? '/' : $path;
    }

    private static function count(string $key): void
    {
        if (! config('page-cache.stats', true)) {
            return;
        }

        Cache::add($key, 0);
        Cache::increment($key);
    }
}
