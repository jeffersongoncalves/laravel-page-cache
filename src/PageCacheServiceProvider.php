<?php

declare(strict_types=1);

namespace JeffersonGoncalves\PageCache;

use JeffersonGoncalves\PageCache\Commands\FlushCommand;
use JeffersonGoncalves\PageCache\Commands\ForgetCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PageCacheServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-page-cache')
            ->hasConfigFile()
            ->hasCommands([FlushCommand::class, ForgetCommand::class]);
    }
}
