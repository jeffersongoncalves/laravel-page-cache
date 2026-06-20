<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use JeffersonGoncalves\PageCache\Middleware\CachePublicPage;

beforeEach(function () {
    CachePublicPage::flush();

    Route::middleware(CachePublicPage::class)->get('/cached', fn () => (string) Str::uuid());
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
    Route::middleware(CachePublicPage::class)->get('/missing', fn () => response('nope', 404));

    $this->get('/missing')->assertStatus(404)->assertHeaderMissing('X-Page-Cache');
    $this->get('/missing')->assertStatus(404)->assertHeaderMissing('X-Page-Cache');
});

it('invalidates every cached page when flush is called', function () {
    $this->get('/cached')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/cached')->assertHeader('X-Page-Cache', 'HIT');

    CachePublicPage::flush();

    $this->get('/cached')->assertHeader('X-Page-Cache', 'MISS');
});

it('stores a separate cache entry per theme cookie', function () {
    $this->withUnencryptedCookie('theme', 'light')
        ->get('/cached')->assertHeader('X-Page-Cache', 'MISS');

    $this->withUnencryptedCookie('theme', 'dark')
        ->get('/cached')->assertHeader('X-Page-Cache', 'MISS');

    $this->withUnencryptedCookie('theme', 'light')
        ->get('/cached')->assertHeader('X-Page-Cache', 'HIT');
});
