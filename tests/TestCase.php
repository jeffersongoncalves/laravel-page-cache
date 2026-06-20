<?php

namespace JeffersonGoncalves\PageCache\Tests;

use JeffersonGoncalves\PageCache\PageCacheServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            PageCacheServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('cache.default', 'array');
    }
}
