<?php

declare(strict_types=1);

namespace JeffersonGoncalves\PageCache\Commands;

use Illuminate\Console\Command;
use JeffersonGoncalves\PageCache\PageCache;

final class ForgetCommand extends Command
{
    protected $signature = 'page-cache:forget {path : The path or URL whose cached variants to invalidate}';

    protected $description = 'Invalidate every cached variant of one path';

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        PageCache::forget($path);

        $this->components->info('Page cache forgotten for ['.PageCache::normalizePath($path).'].');

        return self::SUCCESS;
    }
}
