<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use JeffersonGoncalves\PageCache\Middleware\CachePublicPage;
use JeffersonGoncalves\PageCache\PageCache;

beforeEach(function () {
    PageCache::flush();
    PageCache::resume();

    Route::middleware(['web', CachePublicPage::class])->get('/a', fn () => (string) Str::uuid());
    Route::middleware(['web', CachePublicPage::class])->get('/b', fn () => (string) Str::uuid());
});

it('flushes every cached page from the command line', function () {
    $this->get('/a')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/b')->assertHeader('X-Page-Cache', 'MISS');

    $this->artisan('page-cache:flush')->expectsOutputToContain('Page cache flushed.')->assertSuccessful();

    $this->get('/a')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/b')->assertHeader('X-Page-Cache', 'MISS');
});

it('deletes superseded payloads on flush, including ones stored forever', function () {
    config()->set('page-cache.ttl', 0);

    $this->get('/a')->assertHeader('X-Page-Cache', 'MISS');
    $key = PageCache::key(Request::create('/a'));
    expect(PageCache::pages()->has($key))->toBeTrue();

    $this->artisan('page-cache:flush')->assertSuccessful();

    expect(PageCache::pages()->has($key))->toBeFalse();
});

it('forgets one path from the command line', function () {
    $this->get('/a')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/b')->assertHeader('X-Page-Cache', 'MISS');

    $this->artisan('page-cache:forget', ['path' => '/a/'])->expectsOutputToContain('[a]')->assertSuccessful();

    $this->get('/a')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/b')->assertHeader('X-Page-Cache', 'HIT');
});
