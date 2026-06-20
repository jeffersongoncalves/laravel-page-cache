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
 * path, with a static flush() helper that bumps the version token so model
 * observers can invalidate every cached page at once.
 *
 * Skips authenticated requests so personalised responses are never shared.
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

        /** @var array{content: string, type: string}|null $cached */
        $cached = Cache::get($key);

        if ($cached !== null) {
            return response($cached['content'], 200)
                ->header('Content-Type', $cached['type'])
                ->header('X-Page-Cache', 'HIT');
        }

        $response = $next($request);

        if ($response->getStatusCode() === 200 && $response->getContent() !== false) {
            Cache::put($key, [
                'content' => $response->getContent(),
                'type' => (string) $response->headers->get('Content-Type', 'text/html; charset=UTF-8'),
            ], (int) config('page-cache.ttl', 3600));

            $response->headers->set('X-Page-Cache', 'MISS');
        }

        return $response;
    }

    /** Bump the version token to invalidate every cached page. */
    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }

    private function shouldCache(Request $request): bool
    {
        return (bool) config('page-cache.enabled', true)
            && $request->isMethod('GET')
            && $request->user() === null;
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

        $segments[] = sha1($request->path());

        return implode(':', $segments);
    }

    private static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }
}
