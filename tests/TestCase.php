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
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('database.default', 'testing');
        config()->set('cache.default', 'array');
        config()->set('session.driver', 'array');
    }
}
