<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use JeffersonGoncalves\PageCache\Middleware\CachePublicPage;
use JeffersonGoncalves\PageCache\Middleware\ServeCachedPage;
use JeffersonGoncalves\PageCache\PageCache;

beforeEach(function () {
    PageCache::flush();

    // ServeCachedPage sits before the web group (StartSession); CachePublicPage stays inside it.
    Route::middleware([ServeCachedPage::class, 'web', CachePublicPage::class])
        ->get('/early', fn () => (string) Str::uuid());
});

it('serves a cookieless HIT without a session or Set-Cookie, from the key CachePublicPage stored', function () {
    $first = $this->get('/early');
    $first->assertOk()->assertHeader('X-Page-Cache', 'MISS');
    expect($first->headers->getCookies())->not->toBeEmpty();

    $second = $this->get('/early');
    $second->assertOk()->assertHeader('X-Page-Cache', 'HIT');

    expect($second->getContent())->toBe($first->getContent())
        ->and($second->headers->getCookies())->toBeEmpty()
        ->and(app('session')->isStarted())->toBeFalse();
});

it('leaves requests with a session cookie to the normal stack', function () {
    $this->get('/early')->assertHeader('X-Page-Cache', 'MISS');

    $hit = $this->withCookie(config('session.cookie'), 'abc')->get('/early');

    $hit->assertHeader('X-Page-Cache', 'HIT');
    expect($hit->headers->getCookies())->not->toBeEmpty();
});

it('leaves requests with a remember-me cookie to the normal stack', function () {
    $this->get('/early')->assertHeader('X-Page-Cache', 'MISS');

    $hit = $this->withCookie('remember_web_'.sha1('x'), 'abc')->get('/early');

    expect($hit->headers->getCookies())->not->toBeEmpty();
});

it('leaves requests with an Authorization header to the normal stack', function () {
    $this->get('/early')->assertHeader('X-Page-Cache', 'MISS');

    $hit = $this->withHeader('Authorization', 'Bearer token')->get('/early');

    expect($hit->headers->getCookies())->not->toBeEmpty();
});

it('sends a shared Cache-Control on an early HIT when s_maxage is set', function () {
    config()->set('page-cache.early.s_maxage', 600);

    $this->get('/early')->assertHeader('X-Page-Cache', 'MISS');

    $cacheControl = $this->get('/early')->headers->get('Cache-Control');

    expect($cacheControl)->toContain('public')->toContain('s-maxage=600');
});

it('does nothing while the cache is paused', function () {
    $this->get('/early')->assertHeader('X-Page-Cache', 'MISS');

    PageCache::pause();

    $this->get('/early')->assertOk()->assertHeaderMissing('X-Page-Cache');
});
