<?php

declare(strict_types=1);

namespace JeffersonGoncalves\PageCache\Commands;

use Illuminate\Console\Command;
use JeffersonGoncalves\PageCache\PageCache;

final class FlushCommand extends Command
{
    protected $signature = 'page-cache:flush';

    protected $description = 'Invalidate every full-page cache entry';

    public function handle(): int
    {
        PageCache::flush();

        $this->components->info('Page cache flushed.');

        return self::SUCCESS;
    }
}
