<?php

declare(strict_types=1);

namespace JeffersonGoncalves\PageCache;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Vite;

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

    /**
     * The cache key of a request, shared by CachePublicPage and ServeCachedPage so a page stored by one is served
     * by the other.
     */
    public static function key(Request $request): string
    {
        // Key on the PATH (not full URL) so the query string cannot flood the
        // cache with ?x=1,2,3… variants. Locale and theme are folded in only
        // when enabled, so pre-paint markup driven by the theme cookie is not
        // served to visitors with a different theme.
        $segments = ['page', (string) self::version()];

        if (config('page-cache.key.locale', true)) {
            $segments[] = app()->getLocale();
        }

        if (config('page-cache.key.theme.enabled', true)) {
            $cookie = (string) config('page-cache.key.theme.cookie', 'theme');
            $segments[] = $request->cookie($cookie) === 'light' ? 'light' : 'dark';
        }

        if (config('page-cache.key.accept_encoding', true)) {
            // Vary on the negotiated content encoding so a body compressed for a
            // gzip/br client is never replayed to a client that cannot decode
            // it. The header is normalized (tokens lowercased and sorted) so
            // trivial ordering/whitespace differences collapse to one entry.
            $segments[] = self::normalizeAcceptEncoding((string) $request->header('Accept-Encoding', ''));
        }

        // The per-path version lets forget() drop every variant of one URL.
        $segments[] = sha1($request->path()).'.'.self::pathVersion($request->path());

        if (config('page-cache.include_query_string', true)) {
            // Fold a normalized (recursively sorted) query string into the key
            // so /products?page=2 is a distinct entry from /products?page=1
            // while ?b=2&a=1 and ?a=1&b=2 collapse to the same key.
            $query = $request->query();
            self::recursiveKsort($query);
            $segments[] = sha1(http_build_query($query));
        }

        return implode(':', $segments);
    }

    /** The page stored under key() rebuilt as a HIT response, or null on a MISS. */
    public static function cachedResponse(string $key): ?Response
    {
        /** @var array{content: string, headers: array<string, array<int, string|null>>, nonce?: string|null}|null $cached */
        $cached = Cache::get($key);

        if ($cached === null) {
            return null;
        }

        // The cached markup carries the CSP nonce it was rendered with: hand it back to
        // Vite so a CSP header built after this (laravel-security-headers) matches it.
        if (($cached['nonce'] ?? null) !== null) {
            Vite::useCspNonce($cached['nonce']);
        }

        $response = new Response($cached['content'], 200);

        foreach ($cached['headers'] as $name => $values) {
            $response->headers->set($name, $values);
        }

        $response->headers->set('X-Page-Cache', 'HIT');

        self::recordHit();

        return $response;
    }

    /** The same shape Request::path() returns: no surrounding slashes, "/" for the root. */
    public static function normalizePath(string $path): string
    {
        $path = trim((string) parse_url($path, PHP_URL_PATH), '/');

        return $path === '' ? '/' : $path;
    }

    /**
     * @param  array<array-key, mixed>  $array
     */
    private static function recursiveKsort(array &$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::recursiveKsort($value);
            }
        }

        unset($value);

        ksort($array);
    }

    private static function normalizeAcceptEncoding(string $value): string
    {
        $tokens = [];

        foreach (explode(',', $value) as $part) {
            // Strip any quality value ("gzip;q=0.5" -> "gzip") and whitespace.
            $token = strtolower(trim(explode(';', $part)[0]));

            if ($token !== '') {
                $tokens[$token] = true;
            }
        }

        if ($tokens === []) {
            return 'identity';
        }

        $tokens = array_keys($tokens);
        sort($tokens);

        return implode('+', $tokens);
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
