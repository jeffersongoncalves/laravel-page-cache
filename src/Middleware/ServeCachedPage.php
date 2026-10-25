<?php

declare(strict_types=1);

namespace JeffersonGoncalves\PageCache\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JeffersonGoncalves\PageCache\PageCache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opt-in early path: serves a cache HIT before StartSession runs, so a cookieless
 * guest (most crawler traffic) gets no session row and no Set-Cookie. Prepend it to
 * the web group; it only answers GET requests without a session or remember-me
 * cookie (guests by definition) and lets everything else, including a MISS, fall
 * through to the normal stack where CachePublicPage stores the page as usual.
 */
class ServeCachedPage
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PageCache::isActive() || ! $request->isMethod('GET') || $this->carriesVisitorState($request)) {
            return $next($request);
        }

        // A MISS is counted by CachePublicPage further down the stack.
        $response = PageCache::cachedResponse(PageCache::key($request));

        if ($response === null) {
            return $next($request);
        }

        $sharedMaxAge = (int) config('page-cache.early.s_maxage', 0);

        if ($sharedMaxAge > 0) {
            // No cookie rides on this response, so a CDN may cache it.
            $response->headers->set('Cache-Control', 'public, s-maxage='.$sharedMaxAge);
        }

        return $response;
    }

    private function carriesVisitorState(Request $request): bool
    {
        // Runs before EncryptCookies, so only the cookie names are trusted here.
        foreach ($request->cookies->keys() as $name) {
            if ($name === config('session.cookie') || Str::startsWith((string) $name, 'remember_')) {
                return true;
            }
        }

        return false;
    }
}
