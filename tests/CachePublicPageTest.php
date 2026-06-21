<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use JeffersonGoncalves\PageCache\Middleware\CachePublicPage;

beforeEach(function () {
    CachePublicPage::flush();

    // Registered inside the web group so StartSession runs first and the
    // middleware can tell guests from authenticated visitors.
    Route::middleware(['web', CachePublicPage::class])->get('/cached', fn () => (string) Str::uuid());
});

it('caches a 200 GET response and serves a HIT with the same body on the second hit', function () {
    $first = $this->get('/cached');
    $first->assertOk()->assertHeader('X-Page-Cache', 'MISS');

    $second = $this->get('/cached');
    $second->assertOk()->assertHeader('X-Page-Cache', 'HIT');

    expect($second->getContent())->toBe($first->getContent());
});

it('does not cache authenticated requests', function () {
    $this->actingAs(new User);

    $this->get('/cached')->assertOk()->assertHeaderMissing('X-Page-Cache');
    $this->get('/cached')->assertOk()->assertHeaderMissing('X-Page-Cache');
});

it('does not cache non-200 responses', function () {
    Route::middleware(['web', CachePublicPage::class])->get('/missing', fn () => response('nope', 404));

    $this->get('/missing')->assertStatus(404)->assertHeaderMissing('X-Page-Cache');
    $this->get('/missing')->assertStatus(404)->assertHeaderMissing('X-Page-Cache');
});

it('does not cache when the session has not been started', function () {
    // No web group => StartSession never runs => no started session.
    Route::middleware(CachePublicPage::class)->get('/no-session', fn () => (string) Str::uuid());

    $this->get('/no-session')->assertOk()->assertHeaderMissing('X-Page-Cache');
    $this->get('/no-session')->assertOk()->assertHeaderMissing('X-Page-Cache');
});

it('does not cache responses that set their own cookies', function () {
    Route::middleware(['web', CachePublicPage::class])
        ->get('/with-cookie', fn () => response((string) Str::uuid())->cookie('foo', 'bar'));

    $this->get('/with-cookie')->assertOk()->assertHeaderMissing('X-Page-Cache');
    $this->get('/with-cookie')->assertOk()->assertHeaderMissing('X-Page-Cache');
});

it('is a no-op when caching is disabled', function () {
    config()->set('page-cache.enabled', false);

    $this->get('/cached')->assertOk()->assertHeaderMissing('X-Page-Cache');
    $this->get('/cached')->assertOk()->assertHeaderMissing('X-Page-Cache');
});

it('invalidates every cached page when flush is called', function () {
    $this->get('/cached')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/cached')->assertHeader('X-Page-Cache', 'HIT');

    CachePublicPage::flush();

    $this->get('/cached')->assertHeader('X-Page-Cache', 'MISS');
});

it('stores a separate cache entry per theme cookie', function () {
    // A real app excepts the theme cookie from encryption; disable the
    // EncryptCookies middleware here so the plaintext value is read back.
    $this->withoutMiddleware(EncryptCookies::class);

    $this->withUnencryptedCookie('theme', 'light')
        ->get('/cached')->assertHeader('X-Page-Cache', 'MISS');

    $this->withUnencryptedCookie('theme', 'dark')
        ->get('/cached')->assertHeader('X-Page-Cache', 'MISS');

    $this->withUnencryptedCookie('theme', 'light')
        ->get('/cached')->assertHeader('X-Page-Cache', 'HIT');
});

it('stores a separate cache entry per query string', function () {
    $this->get('/cached?page=1')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/cached?page=2')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/cached?page=1')->assertHeader('X-Page-Cache', 'HIT');
});

it('normalizes query string order into the same cache entry', function () {
    $this->get('/cached?a=1&b=2')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/cached?b=2&a=1')->assertHeader('X-Page-Cache', 'HIT');
});

it('can ignore the query string when configured off', function () {
    config()->set('page-cache.include_query_string', false);

    $this->get('/cached?page=1')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/cached?page=2')->assertHeader('X-Page-Cache', 'HIT');
});

it('replays the stored content type and headers on a cache hit', function () {
    Route::middleware(['web', CachePublicPage::class])->get('/json', fn () => response('{"v":"'.Str::uuid().'"}', 200)
        ->header('Content-Type', 'application/json')
        ->header('X-Custom', 'kept'));

    $first = $this->get('/json');
    $first->assertOk()->assertHeader('X-Page-Cache', 'MISS')
        ->assertHeader('Content-Type', 'application/json')
        ->assertHeader('X-Custom', 'kept');

    $second = $this->get('/json');
    $second->assertOk()->assertHeader('X-Page-Cache', 'HIT')
        ->assertHeader('Content-Type', 'application/json')
        ->assertHeader('X-Custom', 'kept');

    expect($second->getContent())->toBe($first->getContent());
});

it('caches forever when the ttl is zero', function () {
    config()->set('page-cache.ttl', 0);

    $first = $this->get('/cached');
    $first->assertOk()->assertHeader('X-Page-Cache', 'MISS');

    $second = $this->get('/cached');
    $second->assertOk()->assertHeader('X-Page-Cache', 'HIT');

    expect($second->getContent())->toBe($first->getContent());
});
