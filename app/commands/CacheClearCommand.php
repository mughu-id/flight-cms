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
        $root = dirname(__DIR__, 2);
        foreach (['storage/cache/pages', 'storage/cache/twig'] as $dir) {
            foreach (glob($root . '/' . $dir . '/*') ?: [] as $file) {
                is_dir($file) ? null : @unlink($file);
            }
        }
        $this->app()->io()->ok('Caches cleared.');
    }
}
