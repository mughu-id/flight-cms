<?php

declare(strict_types=1);

namespace App\Command;

use flight\commands\AbstractBaseCommand;

class CacheClearCommand extends AbstractBaseCommand
{
    public function __construct(array $config)
    {
        parent::__construct('cache:clear', 'Clear page and Twig caches', $config);
    }

    public function execute(): void
    {
        \App\Core\Cache::clear(dirname(__DIR__, 2));
        $this->app()->io()->ok('Caches cleared.');
    }
}
