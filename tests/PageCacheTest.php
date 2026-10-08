<?php

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

it('forgets every variant of one path and leaves the others cached', function () {
    $this->get('/a')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/a?page=2')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/b')->assertHeader('X-Page-Cache', 'MISS');

    PageCache::forget('https://example.com/a?ignored=1');

    $this->get('/a')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/a?page=2')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/b')->assertHeader('X-Page-Cache', 'HIT');
});

it('pauses and resumes caching at runtime', function () {
    PageCache::pause();

    $this->get('/a')->assertOk()->assertHeaderMissing('X-Page-Cache');
    expect(PageCache::isActive())->toBeFalse();

    PageCache::resume();

    $this->get('/a')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/a')->assertHeader('X-Page-Cache', 'HIT');
});

it('counts hits and misses since the last flush', function () {
    $this->get('/a');
    $this->get('/a');
    $this->get('/a');

    expect(PageCache::stats())->toMatchArray(['hits' => 2, 'misses' => 1, 'hit_ratio' => 0.6667, 'paused' => false]);

    PageCache::flush();

    expect(PageCache::stats())->toMatchArray(['hits' => 0, 'misses' => 0, 'hit_ratio' => null])
        ->and(PageCache::stats()['flushed_at'])->not->toBeNull();
});

it('does not count when stats are disabled', function () {
    config()->set('page-cache.stats', false);

    $this->get('/a');
    $this->get('/a');

    expect(PageCache::stats())->toMatchArray(['hits' => 0, 'misses' => 0]);
});

it('normalizes paths like Request::path()', function () {
    expect(PageCache::normalizePath('/'))->toBe('/')
        ->and(PageCache::normalizePath(''))->toBe('/')
        ->and(PageCache::normalizePath('/pt_BR/projects/'))->toBe('pt_BR/projects')
        ->and(PageCache::normalizePath('https://example.com/pt_BR/projects?page=2'))->toBe('pt_BR/projects');
});

it('keeps CachePublicPage::flush() working for existing callers', function () {
    $this->get('/a')->assertHeader('X-Page-Cache', 'MISS');
    $before = PageCache::version();

    CachePublicPage::flush();

    expect(PageCache::version())->toBe($before + 1);
    $this->get('/a')->assertHeader('X-Page-Cache', 'MISS');
});
