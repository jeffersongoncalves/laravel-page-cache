<?php

declare(strict_types=1);

namespace JeffersonGoncalves\PageCache\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Full-page response cache for stateless public pages. Caches 200 GET
 * responses keyed on a version token + locale + theme cookie + the request
 * path (and, by default, a normalized query string), with a static flush()
 * helper that bumps the version token so model observers can invalidate every
 * cached page at once.
 *
 * To stay safe it refuses to cache anything that could leak per-visitor state:
 * authenticated requests, requests without a started session, and responses
 * that carry their own Set-Cookie headers (forms/CSRF/flash messages) are all
 * passed straight through. Register it AFTER StartSession/auth (inside the web
 * group), never as the outermost middleware.
 */
class CachePublicPage
{
    private const VERSION_KEY = 'pages:version';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldCache($request)) {
            return $next($request);
        }

        $key = $this->cacheKey($request);

        /** @var array{content: string, headers: array<string, array<int, string|null>>}|null $cached */
        $cached = Cache::get($key);

        if ($cached !== null) {
            $response = response($cached['content'], 200);

            foreach ($cached['headers'] as $name => $values) {
                $response->headers->set($name, $values);
            }

            return $response->header('X-Page-Cache', 'HIT');
        }

        $response = $next($request);

        if (! $this->shouldStore($response)) {
            return $response;
        }

        $headers = $response->headers->all();
        // Drop Set-Cookie (per-visitor state) and the original Date header: a
        // stored Date would be served stale on every later HIT, so we let the
        // replayed response generate a fresh one instead.
        unset($headers['set-cookie'], $headers['date']);

        $payload = [
            'content' => (string) $response->getContent(),
            'headers' => $headers,
        ];

        $ttl = (int) config('page-cache.ttl', 3600);

        if ($ttl <= 0) {
            // A TTL of 0 (or below) means "cache forever" rather than silently
            // disabling the cache.
            Cache::forever($key, $payload);
        } else {
            Cache::put($key, $payload, $ttl);
        }

        $response->headers->set('X-Page-Cache', 'MISS');

        return $response;
    }

    /** Bump the version token to invalidate every cached page. */
    public static function flush(): void
    {
        // Atomic bump: read-modify-write (Cache::forever(version() + 1)) loses
        // an increment when two flushes race. Seed the key if missing, then let
        // the store increment it atomically. Old entries keyed on the previous
        // version become unreachable (and expire with their own TTL) instead of
        // being overwritten.
        Cache::add(self::VERSION_KEY, 1);
        Cache::increment(self::VERSION_KEY);
    }

    private function shouldCache(Request $request): bool
    {
        // Guest detection relies on $request->user(), which in turn depends on
        // the session/auth middleware having already run. If no started
        // session is present we cannot tell a guest from a logged-in user, so
        // we refuse to cache rather than risk sharing personalised content.
        if (! $request->hasSession() || ! $request->session()->isStarted()) {
            return false;
        }

        return (bool) config('page-cache.enabled', true)
            && $request->isMethod('GET')
            && $request->user() === null;
    }

    private function shouldStore(Response $response): bool
    {
        // Never cache a response that sets its own cookies: a Set-Cookie header
        // usually carries CSRF tokens, fresh session ids or flash state that
        // must not be replayed to a different visitor.
        if (count($response->headers->getCookies()) > 0) {
            return false;
        }

        // Honour an explicit opt-out. Cookies queued through Laravel's cookie
        // jar are flushed into the response *after* this middleware runs, so a
        // CSRF/session cookie is invisible to the check above. Any controller
        // that renders per-visitor state (a @csrf token, a form, flash data)
        // should send `Cache-Control: no-store` to keep that response out of
        // the shared cache.
        //
        // Only `no-store` is treated as the opt-out: Symfony stamps every
        // response that does not set its own cache headers with the default
        // `Cache-Control: no-cache, private`, so honouring `private`/`no-cache`
        // would disable the cache for practically every page.
        if ($response->headers->hasCacheControlDirective('no-store')) {
            return false;
        }

        return $response->getStatusCode() === 200
            && $response->getContent() !== false;
    }

    private function cacheKey(Request $request): string
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
            $segments[] = $this->normalizeAcceptEncoding((string) $request->header('Accept-Encoding', ''));
        }

        $segments[] = sha1($request->path());

        if (config('page-cache.include_query_string', true)) {
            // Fold a normalized (recursively sorted) query string into the key
            // so /products?page=2 is a distinct entry from /products?page=1
            // while ?b=2&a=1 and ?a=1&b=2 collapse to the same key.
            $query = $request->query();
            $this->recursiveKsort($query);
            $segments[] = sha1(http_build_query($query));
        }

        return implode(':', $segments);
    }

    /**
     * @param  array<array-key, mixed>  $array
     */
    private function recursiveKsort(array &$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->recursiveKsort($value);
            }
        }

        unset($value);

        ksort($array);
    }

    private function normalizeAcceptEncoding(string $value): string
    {
        if ($value === '') {
            return 'identity';
        }

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

    private static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }
}
