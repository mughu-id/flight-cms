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
            $path = $root . '/' . $dir;
            if (is_dir($path)) {
                $this->wipe($path);
            }
        }
        $this->app()->io()->ok('Caches cleared.');
    }

    private function wipe(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..' || $name === '.gitkeep') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $name;
            if (is_dir($path)) {
                $this->wipe($path);
                @rmdir($path);
                continue;
            }
            @unlink($path);
        }
    }
}
